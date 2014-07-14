<?php

namespace Lessphp;

use Lessphp\Parser\TagParser;

class NodeCounter
{
    public $count = 0;
    public $children = array();

    public $name;
    public $child_blocks;
    public $the_block;

    public function __construct($name)
    {
        $this->name = $name;
    }

    public function dump($stack = null)
    {
        if (is_null($stack)) {
            $stack = array();
        }
        $stack[] = $this->getName();
        echo implode(' -> ', $stack)." ($this->count)\n";
        foreach ($this->children as $child) {
            $child->dump($stack);
        }
    }

    public static function compileProperties($c, $block)
    {
        foreach ($block as $name => $value) {
            if ($c->isProperty($name, $value)) {
                echo $c->compileProperty($name, $value)."\n";
            }
        }
    }

    public function compile($c, $path = null)
    {
        if (is_null($path)) $path = array();
        $path[] = $this->name;

        $isVisible = !is_null($this->the_block) || !is_null($this->child_blocks);

        if ($isVisible) {
            echo $c->indent(implode(' ', $path).' {');
            $c->indentLevel++;
            $path = array();

            if ($this->the_block) {
                $this->compileProperties($c, $this->the_block);
            }

            if ($this->child_blocks) {
                foreach ($this->child_blocks as $block) {
                    echo $c->indent(tagparse::compilePaths($block['__tags']).' {');
                    $c->indentLevel++;
                    $this->compileProperties($c, $block);
                    $c->indentLevel--;
                    echo $c->indent('}');
                }
            }
        }

        // compile child nodes
        foreach ($this->children as $node) {
            $node->compile($c, $path);
        }

        if ($isVisible) {
            $c->indentLevel--;
            echo $c->indent('}');
        }

    }

    public function getName()
    {
        if (is_null($this->name)) return "[root]";
        else return $this->name;
    }

    public function getNode($name)
    {
        if (!isset($this->children[$name])) {
            $this->children[$name] = new nodecounter($name);
        }

        return $this->children[$name];
    }

    public function findNode($path)
    {
        $current = $this;
        for ($i = 0; $i < count($path); $i++) {
            $t = TagParser::compileTag($path[$i]);
            $current = $current->getNode($t);
        }

        return $current;
    }

    public function addBlock($path, $block)
    {
        $node = $this->findNode($path);
        if (!is_null($node->the_block)) {
            throw new \Exception("can this happen?");
        }

        unset($block['__tags']);
        $node->the_block = $block;
    }

    public function addToNode($path, $block)
    {
        $node = $this->findNode($path);
        $node->child_blocks[] = $block;
    }
}
