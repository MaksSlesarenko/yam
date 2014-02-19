<?php

namespace Yam\Migrations\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;

class StatusCommand extends AbstractCommand
{
    protected function configure()
    {
        $this
            ->setName($this->getCommandPrefix() . 'status')
            ->setDescription('View the status of a set of migrations.')
            ->addOption('show-versions', null, InputOption::VALUE_NONE, 'This will display a list of all available migrations and their status')
            ->setHelp(<<<EOT
The <info>%command.name%</info> command outputs the status of a set of migrations:

    <info>%command.full_name%</info>

You can output a list of all available migrations and their status with <comment>--show-versions</comment>:

    <info>%command.full_name% --show-versions</info>
EOT
        );

        parent::configure();
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        $configuration = $this->getMigrationConfiguration($input, $output);

        $currentVersion = $configuration->getCurrentVersion();
        if ($currentVersion) {
            $currentVersionFormatted = $configuration->formatVersion($currentVersion) . ' (<comment>'.$currentVersion.'</comment>)';
        } else {
            $currentVersionFormatted = 0;
        }
        $latestVersion = $configuration->getLatestVersion();
        if ($latestVersion) {
            $latestVersionFormatted = $configuration->formatVersion($latestVersion) . ' (<comment>'.$latestVersion.'</comment>)';
        } else {
            $latestVersionFormatted = 0;
        }

        $executedMigrations = $configuration->getMigratedVersions();
        $availableMigrations = $configuration->getAvailableVersions();
        $executedUnavailableMigrations = array_diff($executedMigrations, $availableMigrations);
        $numExecutedUnavailableMigrations = count($executedUnavailableMigrations);
        $newMigrations = count($availableMigrations) - count($executedMigrations);

        $output->writeln("\n <info>==</info> Configuration\n");

        $info = array(
            'Name'                              => $configuration->getName() ? $configuration->getName() : 'Yam Database Migrations',
            'Database Driver'                   => $configuration->getConnection()->getDriver()->getName(),
            'Database Name'                     => $configuration->getConnection()->getDatabase(),
            'Configuration Source'              => $configuration instanceof \Yam\Migrations\Configuration\AbstractFileConfiguration ? $configuration->getFile() : 'manually configured',
            'Version Table Name'                => $configuration->getMigrationsTableName(),
            'Migrations Namespace'              => $configuration->getMigrationsNamespace(),
            'Migrations Directory'              => $configuration->getMigrationsDirectory(),
            'Current Version'                   => $currentVersionFormatted,
            'Latest Version'                    => $latestVersionFormatted,
            'Executed Migrations'               => count($executedMigrations),
            'Executed Unavailable Migrations'   => $numExecutedUnavailableMigrations > 0 ? '<error>'.$numExecutedUnavailableMigrations.'</error>' : 0,
            'Available Migrations'              => count($availableMigrations),
            'New Migrations'                    => $newMigrations > 0 ? '<question>' . $newMigrations . '</question>' : 0
        );
        foreach ($info as $name => $value) {
            $output->writeln('    <comment>>></comment> ' . $name . ': ' . str_repeat(' ', 50 - strlen($name)) . $value);
        }

        $showVersions = $input->getOption('show-versions') ? true : false;
        if ($showVersions === true) {
            if ($migrations = $configuration->getMigrations()) {
                $output->writeln("\n <info>==</info> Available Migration Versions\n");
                $migratedVersions = $configuration->getMigratedVersions();
                foreach ($migrations as $version) {
                    $isMigrated = in_array($version->getVersion(), $migratedVersions);
                    $status = $isMigrated ? '<info>migrated</info>' : '<error>not migrated</error>';
                    $output->writeln('    <comment>>></comment> ' . $configuration->formatVersion($version->getVersion()) . ' (<comment>' . $version->getVersion() . '</comment>)' . str_repeat(' ', 30 - strlen($name)) . $status);
                }
            }

            if ($executedUnavailableMigrations) {
                $output->writeln("\n <info>==</info> Previously Executed Unavailable Migration Versions\n");
                foreach ($executedUnavailableMigrations as $executedUnavailableMigration) {
                    $output->writeln('    <comment>>></comment> ' . $configuration->formatVersion($executedUnavailableMigration) . ' (<comment>' . $executedUnavailableMigration . '</comment>)');
                }
            }
        }
    }
}
