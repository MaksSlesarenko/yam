<?php

namespace Yam\Migrations\Command;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Yaml\Yaml;

class DataDumpCommand extends GenerateCommand
{
    protected function configure()
    {
        parent::configure();

        $this
            ->setName($this->getCommandPrefix() . 'data-dump')
            ->setDescription('Generate a data dump by from database to file.')
            ->addArgument('tables', InputArgument::IS_ARRAY, 'Dump data from table')
            ->addOption('file', 'f', InputOption::VALUE_REQUIRED, 'Dump data to file')
            ->addOption('all', 'a', InputOption::VALUE_NONE, 'Generate for all tables')
            ->setHelp(<<<EOT
The <info>%command.name%</info> command generates schema based on database current information
EOT
            );
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        $configuration = $this->getMigrationConfiguration($input, $output);

        $conn = $configuration->getConnection();

        if ($input->getOption('all')) {
            $schemaManager = $configuration->getConnection()->getSchemaManager();
            $tables = $schemaManager->listTableNames();
        } else {
            $tables = $input->getArgument('tables');
        }

        if (!$tables) {
            throw new \InvalidArgumentException('No tables specified');
        }

        foreach ($tables as $tableName) {
            $path = $input->getOption('file') ?: $tableName . '.yml';

            $result = $conn->fetchAll('SELECT * FROM ' . $conn->quoteIdentifier($tableName));

            if (!$result) {
                $output->writeln(sprintf('Table "<info>%s</info>" is empty.', $tableName));
                continue;
            }
            $path = $this->getSchemaPath($configuration, $path);

            file_put_contents($path, Yaml::dump(array($tableName => $result), 3, 2));

            $output->writeln(sprintf(
                'Saved "<info>%s</info>" rows from "<info>%s</info>" to "<info>%s</info>".',
                count($result),
                $tableName,
                $path
            ));
        }
    }
}
