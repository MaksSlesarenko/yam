<?php

namespace Yam\Migrations\Command;

use Doctrine\DBAL\Platforms\PostgreSqlPlatform;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Yaml\Yaml;
use Yam\Migrations\Configuration\Configuration;

class DataInsertCommand extends GenerateCommand
{
    protected function configure()
    {
        parent::configure();

        $this
            ->setName($this->getCommandPrefix() . 'data-insert')
            ->setDescription('Insert data from file to database.')
            ->addArgument('file', InputArgument::IS_ARRAY, 'File data to insert into database')
            ->addOption('data-map', 'd', InputOption::VALUE_REQUIRED, 'Data map file', 'data.map')
            ->addOption('show-sql', null, InputOption::VALUE_NONE, 'Show sql insert')
            ->addOption('migration', null, InputOption::VALUE_NONE, 'Generate migration')
            ->setHelp(<<<EOT
The <info>%command.name%</info> command generates schema based on database current information
EOT
            );
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        $configuration = $this->getMigrationConfiguration($input, $output);

        $platform = $configuration->getConnection()->getDatabasePlatform();
        $schemaManager = $configuration->getConnection()->getSchemaManager();

        $files = $input->getArgument('file');
        if (!$files) {
            if ($mapFile = $input->getOption('data-map')) {
                if (!file_exists($mapFile)) {
                    $mapFile  = $this->getPath($configuration->getDataDirectory(), $mapFile);
                    if (!file_exists($mapFile)) {
                        $output->writeln(sprintf('<error>File "%s" not found.</error>', $mapFile));
                        return;
                    }
                }
                foreach (file($mapFile) as $file) {
                    $file = trim($file);
                    if (!file_exists($file)) {
                        $file = $this->getPath($configuration->getDataDirectory(), $file);
                        if (!file_exists($file)) {
                            $output->writeln(sprintf('<error>File "%s" not found.</error>', $file));
                            continue;
                        } else {
                            $files[] = $file;
                        }
                    }
                }
            } else {
                foreach (new \DirectoryIterator($configuration->getDataDirectory()) as $file) {
                    /* @var \SplFileObject $file*/
                    if ($file->isFile() && $file->isReadable() && $file->getFilename() !== 'schema.yml') {
                        $files[] = $file->getPathname();
                    }
                }
                if (!$files) {
                    $output->writeln(sprintf(
                        '<error>No files found to import.</error>'
                    ));
                    return;
                }
            }
        }

        $sql = array();
        $conn = $configuration->getConnection();

        $sequences = array();
        if ($platform instanceof PostgreSqlPlatform) {
            foreach ($schemaManager->listSequences() as $sequence) {
                $sequences[] = $sequence->getName();
            }
        }

        foreach ($files as $file) {
            if (!file_exists($file)) {
                $output->writeln(sprintf('<error>File "%s" not found.</error>', $file));
                continue;
            }
            try {
                $fileData = Yaml::parse($file);

                foreach ($fileData as $tableName => $data) {
                    if (!isset($sql[$tableName])) {
                        $sql[$tableName] = array();
                    }
                    foreach ($data as $row) {
                        $quoted = array();
                        foreach ($row as $key => $value) {
                            if (null === $value) {
                                $value = 'NULL';
                            } elseif (0 === $value) {
                                $value = '0';
                            } else {
                                $value = $conn->quote($value);
                            }
                            $quoted[$conn->quoteIdentifier($key)] = $value;
                        }

                        $sql[$tableName][] = 'INSERT INTO ' . $conn->quoteIdentifier($tableName)
                            . ' (' . implode(', ', array_keys($quoted)) . ')'
                            . ' VALUES (' . implode(', ', array_values($quoted)) . ')';
                    }

                    if ($sequences) {
                        $pks = $schemaManager->listTableDetails($tableName)->getPrimaryKeyColumns();
                        if (!$pks) {
                            continue;
                        }

                        $pk = current($pks);
                        $sequenceName = $platform->getIdentitySequenceName($tableName, $pk);
                        if (!in_array($sequenceName, $sequences)) {
                            continue;
                        }

                        $max = $conn->fetchColumn("SELECT MAX(" . $pk . ") FROM " . $conn->quoteIdentifier($tableName));

                        //$schemaManager->listSequences();
                        $sql[$tableName][] = "ALTER SEQUENCE " . $sequenceName . " START WITH " . $max;
                    }
                }
            } catch (\Exception $e) {
                $output->writeln(sprintf(
                    'Error parsing file "<info>%s</info>": "<info>%s</info>".',
                    $file,
                    $e->getMessage()
                ));
            }
        }

        if ($input->getOption('migration')) {
            $up = array();
            foreach ($sql as $tableSql) {
                $up += $tableSql;
            }
            $version = date('YmdHis');
            $path = $this->generateMigration($configuration, $input, $version, $up, array());

            $output->writeln(sprintf('Generated new migration class to "<info>%s</info>".', $path));
        } else {
            foreach ($sql as $tableName => $tableSql) {
                $affected = 0;
                $total = count($tableSql);

                foreach ($tableSql as $query) {
                    try {
                        if ($input->getOption('show-sql')) {
                            $output->writeln($query);
                        } else {
                            $affected += $conn->executeUpdate($query);
                        }
                    } catch (\Exception $e) {
                        if (OutputInterface::VERBOSITY_VERBOSE <= $output->getVerbosity()) {
                            $output->writeln(sprintf('<error>%s</error>', $e->getMessage()));
                        }
                    }
                }
                if (!$input->getOption('show-sql')) {
                    $output->writeln(sprintf(
                        'Inserted "<info>%s</info>" of "<info>%s</info> " rows into "<info>%s</info> table".',
                        $affected,
                        $total,
                        $tableName
                    ));
                }
            }
        }
    }

    public function getDataSql(Configuration $configuration, $tableName)
    {
        $conn = $configuration->getConnection();

        $data = array_map(function($value) use ($conn) {
            return $conn->quote($value);
        }, $data);

        return 'INSERT INTO ' . $conn->quoteIdentifier($tableName) . ' ('
             . implode(', ', array_keys($data)) . ')' .
            ' VALUES (' . implode(', ', array_values($data)) . ')';
    }
}
