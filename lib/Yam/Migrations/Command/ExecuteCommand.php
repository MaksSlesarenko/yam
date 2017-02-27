<?php

namespace Yam\Migrations\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

class ExecuteCommand extends AbstractCommand
{
    protected function configure()
    {
        $this
            ->setName($this->getCommandPrefix() . 'execute')
            ->setDescription('Execute a single migration version up or down manually.')
            ->addArgument('version', InputArgument::REQUIRED, 'The version to execute.', null)
            ->addOption('write-sql', null, InputOption::VALUE_NONE, 'The path to output the migration SQL file instead of executing it.')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Execute the migration as a dry run.')
            ->addOption('up', null, InputOption::VALUE_NONE, 'Execute the migration up.')
            ->addOption('down', null, InputOption::VALUE_NONE, 'Execute the migration down.')
            ->setHelp(<<<EOT
The <info>%command.name%</info> command executes a single migration version up or down manually:

    <info>%command.full_name% YYYYMMDDHHMMSS</info>

If no <comment>--up</comment> or <comment>--down</comment> option is specified it defaults to up:

    <info>%command.full_name% YYYYMMDDHHMMSS --down</info>

You can also execute the migration as a <comment>--dry-run</comment>:

    <info>%command.full_name% YYYYMMDDHHMMSS --dry-run</info>

You can output the would be executed SQL statements to a file with <comment>--write-sql</comment>:

    <info>%command.full_name% YYYYMMDDHHMMSS --write-sql</info>

Or you can also execute the migration without a warning message which you need to interact with:

    <info>%command.full_name% --no-interaction</info>
EOT
        );

        parent::configure();
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        $version = $input->getArgument('version');
        $direction = $input->getOption('down') ? 'down' : 'up';

        $configuration = $this->getMigrationConfiguration($input, $output);
        $version = $configuration->getVersion($version);

        if ($path = $input->getOption('write-sql')) {
            $path = is_bool($path) ? getcwd() : $path;
            $version->writeSqlFile($path, $direction);
        } else {
            $noInteraction = $input->getOption('no-interaction') ? true : false;
            if ($noInteraction === true) {
                $version->execute($direction, $input->getOption('dry-run') ? true : false);
            } else {
                $confirmation = $this->getHelper('question')->ask($input, $output, '<question>WARNING! You are about to execute a database migration that could result in schema changes and data lost. Are you sure you wish to continue? (y/n)</question>', false);
                if ($confirmation === true) {
                    $version->execute($direction, $input->getOption('dry-run') ? true : false);
                } else {
                    $output->writeln('<error>Migration cancelled!</error>');
                }
            }
        }
    }
}
