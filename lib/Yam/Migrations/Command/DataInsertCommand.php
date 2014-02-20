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

        $tables = array();
        foreach ($files as $file) {
            if (!file_exists($file)) {
                $output->writeln(sprintf('<error>File "%s" not found.</error>', $file));
                continue;
            }
            try {
                $fileData = Yaml::parse($file);

                $conn = $configuration->getConnection();

                $affected = 0;
                $total = 0;
                foreach ($fileData as $tableName => $data) {
                    $tables[$tableName] = 1;

                    $total += count($data);
                    foreach ($data as $row) {
                        try {
                            $affected += $conn->insert($tableName, $row);
                        } catch (\Exception $e) {
                            $output->writeln(sprintf('<error>%s</error>', $e->getMessage()));
                        }
                    }
                }

                $output->writeln(sprintf(
                    'Inserted "<info>%s</info>" of "<info>%s</info> " rows from "<info>%s</info> file".',
                    $affected,
                    $total,
                    $file
                ));
            } catch (\Exception $e) {

                $output->writeln(sprintf(
                    'Error importing file "<info>%s</info>": "<info>%s</info>".',
                    $file,
                    $e->getMessage()
                ));
            }
        }

        if ($platform instanceof PostgreSqlPlatform) {
            $sequences = array();
            foreach ($schemaManager->listSequences() as $sequence) {
                $sequences[] = $sequence->getName();
            }

            foreach ($tables as $tableName => $nothing) {
                $pks = $schemaManager->listTableDetails($tableName)->getPrimaryKeyColumns();
                if (!$pks) {
                    continue;
                }

                $pk = current($pks);
                $sequenceName = $platform->getIdentitySequenceName($tableName, $pk);
                if (!in_array($sequenceName, $sequences)) {
                    continue;
                }

                //$schemaManager->listSequences();
                $tableName = $conn->quoteIdentifier($tableName);
                $conn->exec("SELECT setval('" . $sequenceName . "', (SELECT MAX(" . $pk . ") FROM " . $tableName . "))");

                $output->writeln(sprintf(
                    'Updated sequences for table "<info>%s</info>".',
                    $tableName
                ));
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
