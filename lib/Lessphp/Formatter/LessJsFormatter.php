<?php

namespace Lessphp\Formatter;

class LessJsFormatter extends ClassicFormatter
{
    public $disableSingle = true;
    public $breakSelectors = true;
    public $assignSeparator = ": ";
    public $selectorSeparator = ",";
}
