<?php

namespace Yam\Migrations\Command;

use Yam\Migrations\SchemaConverter;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Yaml\Yaml;

class SchemaReverseCommand extends GenerateCommand
{
    protected function configure()
    {
        parent::configure();

        $this
            ->setName($this->getCommandPrefix() . 'schema-reverse')
            ->setDescription('Generate a schema file from your current database.')
            ->setHelp(<<<EOT
The <info>%command.name%</info> command generates schema based on database current information
EOT
            );
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        $configuration = $this->getMigrationConfiguration($input, $output);

        $schemaManager = $configuration->getConnection()->getSchemaManager();

        $schemaArray = SchemaConverter::toArray($schemaManager);

        $path = $this->getPath($configuration->getSchemaDirectory(), $input->getOption('schema'));

        file_put_contents($path, Yaml::dump($schemaArray, 100, 2));

        $output->writeln(sprintf('Current database status is saved to "<info>%s</info>".', $path));
    }


}
