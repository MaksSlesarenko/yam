<?php

namespace Yam\Migrations\Command;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Yam\Migrations\Configuration\Configuration;
use Yam\Migrations\SchemaConverter;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Yaml\Yaml;

class CleanCommand extends GenerateCommand
{
    protected function configure()
    {
        parent::configure();

        $this
            ->setName($this->getCommandPrefix() . 'clean')
            ->setDescription('Clean database.')
            ->addArgument('table', InputArgument::IS_ARRAY, 'Table to reverse', null)
            ->addOption('indexes', null, InputOption::VALUE_NONE, 'Clean indexes')
            ->addOption('sequences', null, InputOption::VALUE_NONE, 'Clean sequences')
            ->addOption('rows', null, InputOption::VALUE_NONE, 'Clean table rows')
            ->addOption('foreign-keys', null, InputOption::VALUE_NONE, 'Clean foreign keys')
            ->addOption('all-tables', null, InputOption::VALUE_NONE, 'Apply to all tables')
            ->addOption('show-sql', null, InputOption::VALUE_NONE, 'Show sql only')
            ->setHelp(<<<EOT
The <info>%command.name%</info> command cleans up database
EOT
            );
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        $configuration = $this->getMigrationConfiguration($input, $output);

        $conn = $configuration->getConnection();
        $schemaManager = $configuration->getConnection()->getSchemaManager();
        $platform = $conn->getDatabasePlatform();



        if ($input->hasOption('all-tables')) {
            $tables = array();
            foreach ($schemaManager->listTableNames() as $tableName) {
                $tables[] = $tableName;
            }
        } else {
            $tables = $input->getArgument('tables');
        }

        if (!$tables) {
            throw new \InvalidArgumentException('No tables specified');
        }

        $sql = array();

        if (!$input->getOption('indexes') && !$input->getOption('foreign-keys') && !$input->getOption('rows')) {
            throw new \InvalidArgumentException('Specify what to clean indexes or foreign keys');
        }

        foreach ($tables as $tableName) {
            if ($input->getOption('indexes')) {
                foreach ($schemaManager->listTableIndexes($tableName) as $index) {
                    if ($index->isPrimary()) {
                        continue;
                    }
                    $sql[] = "DROP INDEX " . $index->getQuotedName($platform) . "";
                }
            }
            if ($input->getOption('foreign-keys')) {
                foreach ($schemaManager->listTableForeignKeys($tableName) as $fKey) {
                    $fkName = $fKey->getQuotedName($platform);
                    $sql[] = "ALTER TABLE " . $conn->quoteIdentifier($tableName) . " DROP CONSTRAINT " . $fkName;
                }
            }

            if ($input->getOption('rows')) {
                $sql[] = "DELETE FROM " . $conn->quoteIdentifier($tableName);
            }
        }
        if ($input->getOption('sequences')) {
            foreach ($schemaManager->listSequences() as $sequence) {
                $sequenceName = $sequence->getQuotedName($platform);
                $sql[] = "DROP SEQUENCE " . $sequenceName;
            }
        }

        foreach ($sql as $query) {
            try {
                if ($input->getOption('show-sql')) {
                    $output->writeln(sprintf(
                        '<info>%s</info>;',
                        $query
                    ));
                    continue;
                }
                $conn->beginTransaction();

                $conn->exec($query);

                $output->writeln(sprintf(
                    'Executed "<info>%s</info>".',
                    $query
                ));


                $conn->commit();

            } catch (\Exception $e) {

                $output->writeln(sprintf(
                    'Error "<info>%s</info>": "<error>%s</error>".',
                    $query,
                    $e->getMessage()
                ));

                $conn->rollback();
            }
        }
    }
}
