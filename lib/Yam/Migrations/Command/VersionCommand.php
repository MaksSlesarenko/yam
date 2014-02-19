<?php

namespace Yam\Migrations\Command;

use Yam\Migrations\MigrationException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

class VersionCommand extends AbstractCommand
{
    protected function configure()
    {
        $this
            ->setName($this->getCommandPrefix() . 'version')
            ->setDescription('Manually add and delete migration versions from the version table.')
            ->addArgument('version', InputArgument::REQUIRED, 'The version to add or delete.', null)
            ->addOption('add', null, InputOption::VALUE_NONE, 'Add the specified version.')
            ->addOption('delete', null, InputOption::VALUE_NONE, 'Delete the specified version.')
            ->setHelp(<<<EOT
The <info>%command.name%</info> command allows you to manually add and delete migration versions from the version table:

    <info>%command.full_name% YYYYMMDDHHMMSS --add</info>

If you want to delete a version you can use the <comment>--delete</comment> option:

    <info>%command.full_name% YYYYMMDDHHMMSS --delete</info>
EOT
        );

        parent::configure();
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        $configuration = $this->getMigrationConfiguration($input, $output);

        if ($input->getOption('add') === false && $input->getOption('delete') === false) {
            throw new \InvalidArgumentException('You must specify whether you want to --add or --delete the specified version.');
        }

        $version = $input->getArgument('version');
        $markMigrated = $input->getOption('add') ? true : false;

        if ( ! $configuration->hasVersion($version)) {
            throw MigrationException::unknownMigrationVersion($version);
        }

        $version = $configuration->getVersion($version);
        if ($markMigrated && $configuration->hasVersionMigrated($version)) {
            throw new \InvalidArgumentException(sprintf('The version "%s" already exists in the version table.', $version));
        }

        if ( ! $markMigrated && ! $configuration->hasVersionMigrated($version)) {
            throw new \InvalidArgumentException(sprintf('The version "%s" does not exist in the version table.', $version));
        }

        if ($markMigrated) {
            $version->markMigrated();
        } else {
            $version->markNotMigrated();
        }
    }
}
