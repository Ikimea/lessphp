<?php

namespace Lessphp\Console;

use Symfony\Component\Console\Application as BaseApplication;
use Lessphp\Command\ParserCommand;

class Application extends BaseApplication
{

    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct('Lessphp', '1.0 Powered with by Ikimea');


        $this->add(new ParserCommand());
    }
}