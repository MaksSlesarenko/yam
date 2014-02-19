<?php

namespace Yam\Migrations\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Yaml\Yaml;

class DataInsertCommand extends GenerateCommand
{
    protected function configure()
    {
        parent::configure();

        $this
            ->setName($this->getCommandPrefix() . 'data-insert')
            ->setDescription('Insert data from file to database.')
            ->addOption('file', 'f', InputOption::VALUE_REQUIRED, 'Insert data to database')
            ->setHelp(<<<EOT
The <info>%command.name%</info> command generates schema based on database current information
EOT
            );
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        $configuration = $this->getMigrationConfiguration($input, $output);

        $schemaManager = $configuration->getConnection()->getSchemaManager();

        $files = array();
        if ($input->getOption('file')) {
            $file = $this->getSchemaPath($configuration, $input->getOption('file'));
            if (!file_exists($file)) {
                $output->writeln(sprintf('<error>File "%s" not found.</error>', $file));
                return;
            }
            $files[] = $file;
        } else {
            foreach (new \DirectoryIterator($configuration->getSchemaDirectory()) as $file) {
                /* @var \SplFileObject $file*/
                if ($file->isFile() && $file->isReadable() && $file->getFilename() !== 'schema.yml') {
                    $files[] = $file->getPathname();
                }
            }
        }
        foreach ($files as $file) {
            try {
                $fileData = Yaml::parse($file);

                $conn = $configuration->getConnection();

                $affected = 0;
                $total = 0;
                foreach ($fileData as $tableName => $data) {
                    $total += count($data);
                    foreach ($data as $row) {
                        try {
                            $affected += $conn->insert($tableName, $row);
//                            $schemaManager->listSequences()
//                            $conn->exec("SELECT setval('report_fx_gain_lose_id_seq', (SELECT MAX(id) FROM report_fx_gain_lose))");
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
    }


}
