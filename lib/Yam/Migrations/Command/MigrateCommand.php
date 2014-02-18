<?php

namespace Yam\Migrations\Command;

use Yam\Migrations\Migration;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

class MigrateCommand extends AbstractCommand
{
    protected function configure()
    {
        $this
            ->setName('yam:migrate')
            ->setDescription('Execute a migration to a specified version or the latest available version.')
            ->addArgument('version', InputArgument::OPTIONAL, 'The version to migrate to.', null)
            ->addOption('write-sql', null, InputOption::VALUE_NONE, 'The path to output the migration SQL file instead of executing it.')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Execute the migration as a dry run.')
            ->setHelp(<<<EOT
The <info>%command.name%</info> command executes a migration to a specified version or the latest available version:

    <info>%command.full_name%</info>

You can optionally manually specify the version you wish to migrate to:

    <info>%command.full_name% YYYYMMDDHHMMSS</info>

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

        $configuration = $this->getMigrationConfiguration($input, $output);
        $migration = new Migration($configuration);

        $this->outputHeader($configuration, $output);

        $noInteraction = !$input->isInteractive();

        $executedMigrations = $configuration->getMigratedVersions();
        $availableMigrations = $configuration->getAvailableVersions();
        $executedUnavailableMigrations = array_diff($executedMigrations, $availableMigrations);

        if ($executedUnavailableMigrations) {
            $output->writeln(sprintf('<error>WARNING! You have %s previously executed migrations in the database that are not registered migrations.</error>', count($executedUnavailableMigrations)));
            foreach ($executedUnavailableMigrations as $executedUnavailableMigration) {
                $output->writeln('    <comment>>></comment> ' . $configuration->formatVersion($executedUnavailableMigration) . ' (<comment>' . $executedUnavailableMigration . '</comment>)');
            }

            if ( ! $noInteraction) {
                $confirmation = $this->getHelper('dialog')->askConfirmation($output, '<question>Are you sure you wish to continue? (y/n)</question>', false);
                if ( ! $confirmation) {
                    $output->writeln('<error>Migration cancelled!</error>');

                    return 1;
                }
            }
        }

        if ($path = $input->getOption('write-sql')) {
            $path = is_bool($path) ? getcwd() : $path;
            $migration->writeSqlFile($path, $version);
        } else {
            $dryRun = $input->getOption('dry-run') ? true : false;

            // warn the user if no dry run and interaction is on
            if ( ! $dryRun && ! $noInteraction) {
                $confirmation = $this->getHelper('dialog')->askConfirmation($output, '<question>WARNING! You are about to execute a database migration that could result in schema changes and data lost. Are you sure you wish to continue? (y/n)</question>', false);
                if ( ! $confirmation) {
                    $output->writeln('<error>Migration cancelled!</error>');

                    return 1;
                }
            }

            $sql = $migration->migrate($version, $dryRun);

            if ( ! $sql) {
                $output->writeln('<comment>No migrations to execute.</comment>');
            }
        }
    }
}
