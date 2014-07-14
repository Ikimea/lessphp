<?php

namespace Lessphp;
use Lessphp\Parser\TagParser;

/**
 * create a less file from a css file by combining blocks where appropriate
 */
class Lessify extends Lessc
{
    protected $dependencies = array();
    protected $buffer;

    public function parse($str = null, $initialVariables = null)
    {
        $this->prepareParser($str ? $str : $this->buffer);
        while (false !== $this->parseChunk());

        $root = new NodeCounter(null);

        // attempt to preserve some of the block order
        $order = array();

        $visitedTags = array();
        foreach (end($this->env) as $name => $block) {
            if (!$this->isBlock($name, $block)) continue;
            if (isset($visitedTags[$name])) continue;

            foreach ($block['__tags'] as $t) {
                $visitedTags[$t] = true;
            }

            // skip those with more than 1
            if (count($block['__tags']) == 1) {
                $p = new TagParser(end($block['__tags']));
                $path = $p->parse();
                $root->addBlock($path, $block);
                $order[] = array('compressed', $path, $block);
                continue;
            } else {
                $common = null;
                $paths = array();
                foreach ($block['__tags'] as $rawtag) {
                    $p = new TagParser($rawtag);
                    $paths[] = $path = $p->parse();
                    if (is_null($common)) $common = $path;
                    else {
                        $new_common = array();
                        foreach ($path as $tag) {
                            $head = array_shift($common);
                            if ($tag == $head) {
                                $new_common[] = $head;
                            } else break;
                        }
                        $common = $new_common;
                        if (empty($common)) {
                            // nothing in common
                            break;
                        }
                    }
                }

                if (!empty($common)) {
                    $new_paths = array();
                    foreach ($paths as $p) $new_paths[] = array_slice($p, count($common));
                    $block['__tags'] = $new_paths;
                    $root->addToNode($common, $block);
                    $order[] = array('compressed', $common, $block);
                    continue;
                }

            }

            $order[] = array('none', $block['__tags'], $block);
        }

        $compressed = $root->children;
        foreach ($order as $item) {
            list($type, $tags, $block) = $item;
            if ($type == 'compressed') {
                $top = TagParser::compileTag(reset($tags));
                if (isset($compressed[$top])) {
                    $compressed[$top]->compile($this);
                    unset($compressed[$top]);
                }
            } else {
                echo $this->indent(implode(', ', $tags).' {');
                $this->indentLevel++;
                nodecounter::compileProperties($this, $block);
                $this->indentLevel--;
                echo $this->indent('}');
            }
        }
    }

    /**
     * Overrides parent prepareParser to insert @import statements into the buffer for each dependency.
     *
     * @param $buff
     */
    protected function prepareParser($buff) {
        $this->env = null;
        $this->expandStack = array();
        $this->indentLevel = 0;
        $this->count = 0;
        $this->line = 1;

        // Build import list
        $imports = '';
        foreach ( $this->dependencies as $file_name ) {
            $imports .= "@import '$file_name';\n";
        }
        // Inject imports statements for each dependency into the buffer
        $buff = $imports . $buff;
        $this->buffer = $this->removeComments($buff);
        $this->pushBlock(null); // set up global scope

        // trim whitespace on head
        if (preg_match('/^\s+/', $this->buffer, $m)) {
            $this->line  += substr_count($m[0], "\n");
            $this->buffer = ltrim($this->buffer);
        }
    }

    // remove comments from $text
    // todo: make it work for all functions, not just url
    function removeComments($text) {
        $look = array(
            'url(', '//', '/*', '"', "'"
        );

        $out = '';
        $min = null;
        $done = false;
        while (true) {
            // find the next item
            foreach ($look as $token) {
                $pos = strpos($text, $token);
                if ($pos !== false) {
                    if (!isset($min) || $pos < $min[1]) $min = array($token, $pos);
                }
            }

            if (is_null($min)) break;

            $count = $min[1];
            $skip = 0;
            $newlines = 0;
            switch ($min[0]) {
                case 'url(':
                    if (preg_match('/url\(.*?\)/', $text, $m, 0, $count))
                        $count += strlen($m[0]) - strlen($min[0]);
                    break;
                case '"':
                case "'":
                    if (preg_match('/'.$min[0].'.*?'.$min[0].'/', $text, $m, 0, $count))
                        $count += strlen($m[0]) - 1;
                    break;
                case '//':
                    $skip = strpos($text, "\n", $count);
                    if ($skip === false) $skip = strlen($text) - $count;
                    else $skip -= $count;
                    break;
                case '/*':
                    if (preg_match('/\/\*.*?\*\//s', $text, $m, 0, $count)) {
                        if (!strncmp($m[0], "/*!", 3) ||
                            preg_match("/(license|copyright)/smi", $m[0])) {
                            /* preserve comments with a ! and comments that
                            * mention license or copyright */
                            $skip = 0;
                            /* ensure that we look past the end of this comment,
                            * otherwise we may detect http:// as a partial
                            * single line comment in a subsequent iteration */
                            $count += strlen($m[0]) - strlen($min[0]);
                        } else {
                            $skip = strlen($m[0]);
                            $newlines = substr_count($m[0], "\n");
                        }
                    }
                    break;
            }

            if ($skip == 0) $count += strlen($min[0]);

            $out .= substr($text, 0, $count).str_repeat("\n", $newlines);
            $text = substr($text, $count + $skip);

            $min = null;
        }

        return $out.$text;
    }

    /* environment functions */

    // push a new block on the stack, used for parsing
    function pushBlock($tags) {
        $b = new \stdclass;
        $b->parent = $this->env;

        $b->tags = $tags;
        $b->props = array();
        $b->children = array();

        $this->env = $b;
        return $b;
    }

    // push a block that doesn't multiply tags
    function pushSpecialBlock($name) {
        $b = $this->pushBlock(array($name));
        $b->no_multiply = true;
        return $b;
    }

    // used for compiliation variable state
    function pushEnv($block = NULL)
    {
        $e = new \stdclass;
        $e->parent = $this->env;

        $this->store = array();

        $this->env = $e;
        return $e;
    }
}
