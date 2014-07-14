<?php

namespace Lessphp\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Output\OutputInterface;
use Lessphp\Lessify;


class ParserCommand extends Command
{
    /**
     * {@inheritDoc}
     */
    protected function configure()
    {
        $this
            ->setName('parse')
            ->addArgument(
                'name',
                InputArgument::OPTIONAL,
                'file parse'
            );

    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $name = $input->getArgument('name');

        try  {
            $parser = new Lessify($name);
            $output->writeln( $parser->parse());
        } catch (\Exception $e) {
            $output->writeln("<error>Fatal error: ".$e->getMessage()."</error>\n");
        }
    }
}