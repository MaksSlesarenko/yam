<?php

namespace Yam\Migrations\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class LatestCommand extends AbstractCommand
{
    protected function configure()
    {
        $this
            ->setName($this->getCommandPrefix() . 'latest')
            ->setDescription('Outputs the latest version number')
        ;

        parent::configure();
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        $configuration = $this->getMigrationConfiguration($input, $output);

        $output->writeln($configuration->getLatestVersion());
    }
}
