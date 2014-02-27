<?php

namespace Yam\Migrations\Command;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Yaml\Yaml;

class SequenceUpdateCommand extends GenerateCommand
{
    protected function configure()
    {
        parent::configure();

        $this
            ->setName($this->getCommandPrefix() . 'sequence-update')
            ->setDescription('Update table sequences.')
            ->addArgument('sequence', InputArgument::IS_ARRAY, 'Sequence to update.')
            ->addOption('all', 'a', InputOption::VALUE_NONE, 'Update for all tables')
            ->setHelp(<<<EOT
The <info>%command.name%</info> command updates sequences for tables
EOT
            );
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        $configuration = $this->getMigrationConfiguration($input, $output);

        $conn = $configuration->getConnection();

        if ($input->getOption('all')) {
            $schemaManager = $configuration->getConnection()->getSchemaManager();
            $sequenceList = array();
            foreach ($schemaManager->listSequences() as $sequence) {
                $sequenceList[] = $sequence->getName();
            }
        } else {
            $sequenceList = $input->getArgument('sequence');
        }

        if (!$sequenceList) {
            throw new \InvalidArgumentException('No sequences specified');
        }

        foreach ($sequenceList as $sequenceName) {
            try {
                $conn->beginTransaction();

                if (preg_match('%(.+)_id_seq.*%', $sequenceName, $matches)) {
                    $tableName = $conn->quoteIdentifier($matches[1]);
                    $pk = $conn->quoteIdentifier('id');
                } else {
                    $output->writeln(sprintf('Cannot detect table name from "<info>%s</info>".', $sequenceName));
                    continue;
                }

                $conn->exec("SELECT setval('" . $sequenceName . "', (SELECT MAX(" . $pk  . ") FROM " . $tableName . "))");


                $output->writeln(sprintf(
                    'Updated "<info>%s</info>" on "<info>%s</info>".',
                    $sequenceName,
                    $tableName
                ));

                $conn->commit();

            } catch (\Exception $e) {

                $output->writeln(sprintf(
                    'Error updating "<info>%s</info>" on "<info>%s</info>" <error>%s</error>.',
                    $sequenceName,
                    $tableName,
                    $e->getMessage()
                ));

                $conn->rollback();
            }
        }
    }
}
