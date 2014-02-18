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
            ->setName('yam:data-dump')
            ->setDescription('Generate a data dump by from database to file.')
            ->addArgument('table', InputArgument::REQUIRED, 'Dump data from table')
            ->addOption('file', 'f', InputOption::VALUE_REQUIRED, 'Dump data to file')
            ->setHelp(<<<EOT
The <info>%command.name%</info> command generates schema based on database current information
EOT
            );
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        $configuration = $this->getMigrationConfiguration($input, $output);

        $conn = $configuration->getConnection();

        $tableName = $input->getArgument('table');
        $path = $input->getOption('file') ?: $tableName . '.yml';

        $result = $conn->fetchAll('SELECT * FROM ' . $conn->quoteIdentifier($tableName));

        $path = $this->getSchemaPath($configuration, $path);

        file_put_contents($path, Yaml::dump(array($tableName => $result)));

        $output->writeln(sprintf('Saved "<info>%s</info>" rows to "<info>%s</info>".', count($result), $path));
    }
}
