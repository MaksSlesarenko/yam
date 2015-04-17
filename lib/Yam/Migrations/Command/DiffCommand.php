<?php

namespace Yam\Migrations\Command;

use Yam\Migrations\Configuration\Configuration;
use Yam\Migrations\SchemaConverter;
use Doctrine\DBAL\Schema\SchemaConfig;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Yaml\Yaml;

class DiffCommand extends GenerateCommand
{
    protected function configure()
    {
        parent::configure();

        $this
            ->setName($this->getCommandPrefix() . 'diff')
            ->setDescription('Generate a migration by comparing your current database to your mapping information.')
            //->addOption('show-sql', null, InputOption::VALUE_NONE, 'Data map file', 'data.map')
            ->setHelp(<<<EOT
The <info>%command.name%</info> command generates a migration by comparing your current database to your mapping information:

    <info>%command.full_name%</info>
EOT
            )
            ->addOption('filter-expression', null, InputOption::VALUE_OPTIONAL, 'Tables which are filtered by Regular Expression.')
            ->addOption('show-sql-up', null, InputOption::VALUE_NONE, 'Show sql')
            ->addOption('show-sql-down', null, InputOption::VALUE_NONE, 'Show sql')
        ;
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        $configuration = $this->getMigrationConfiguration($input, $output);

        $conn = $configuration->getConnection();

        $platform = $conn->getDatabasePlatform();

        $schemaFile = $this->getPath($configuration->getSchemaDirectory(), $input->getOption('schema'));

        if (!file_exists($schemaFile)) {
            $output->writeln(sprintf('<error>File "%s" not found.</error>', $schemaFile));
            return;
        }
        $schemaConfig = new SchemaConfig();
        $schemaConfig->setMaxIdentifierLength($conn->getDatabasePlatform()->getMaxIdentifierLength());

        $params = $conn->getParams();
        if (isset($params['defaultTableOptions'])) {
            $schemaConfig->setDefaultTableOptions($params['defaultTableOptions']);
        }


        $schemaArray = Yaml::parse($schemaFile);

        $toSchema = SchemaConverter::toSchema($schemaArray);
        $fromSchema = $conn->getSchemaManager()->createSchema();

        $up = $fromSchema->getMigrateToSql($toSchema, $platform);
        $down = $fromSchema->getMigrateFromSql($toSchema, $platform);

        $up = $this->removeMigrationTableDiff($configuration, $up);
        $down = $this->removeMigrationTableDiff($configuration, $down);

        if (!$up && !$down) {
            $output->writeln('<error>No changes detected in your mapping information.</error>');

            return;
        }

        if ($input->getOption('show-sql-up')) {
            $output->writeln('');
            $output->writeln('<info>SQL to upgrade database:</info>');
            $output->writeln($up);
        }
        if ($input->getOption('show-sql-down')) {
            $output->writeln('');
            $output->writeln('<info>SQL to downgrade database:</info>');
            $output->writeln($down);
        }
        if (!$input->getOption('show-sql-up') && !$input->getOption('show-sql-down')) {
            $version = date('YmdHis');
            $path = $this->generateMigration($configuration, $input, $version, $up, $down);

            $output->writeln(sprintf('Generated new migration class to "<info>%s</info>" from schema differences.', $path));
        }
    }

    protected function removeMigrationTableDiff($configuration, array $sql)
    {
        foreach ($sql as $i => $query) {
            if (strpos($query, $configuration->getMigrationsTableName()) !== false) {
                unset($sql[$i]);
            }
        }
        return $sql;
    }
}
