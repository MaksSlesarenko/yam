<?php

namespace Yam\Migrations\Command;

use Yam\Migrations\Configuration\YamlConfiguration;
use Yam\Migrations\Configuration\Configuration;
use Yam\Migrations\OutputWriter;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;

abstract class AbstractCommand extends Command
{
    /**
     * @var Configuration
     */
    private $configuration;

    protected function configure()
    {
        $cDescription = 'The path to a migrations configuration file.';
        $sDescription = 'The path to a schema configuration file.';
        $this->addOption('configuration', 'c', InputOption::VALUE_OPTIONAL, $cDescription, 'migrations.yml');
        $this->addOption('schema', null, InputOption::VALUE_OPTIONAL, $sDescription, 'schema.yml');
    }

    protected function outputHeader(Configuration $configuration, OutputInterface $output)
    {
        $name = $configuration->getName();
        $name = $name ? $name : 'Database Migration';
        $name = str_repeat(' ', 20) . $name . str_repeat(' ', 20);
        $output->writeln('<question>' . str_repeat(' ', strlen($name)) . '</question>');
        $output->writeln('<question>' . $name . '</question>');
        $output->writeln('<question>' . str_repeat(' ', strlen($name)) . '</question>');
        $output->writeln('');
    }

    public function setMigrationConfiguration(Configuration $config)
    {
        $this->configuration = $config;
    }

    /**
     * @param InputInterface  $input
     * @param OutputInterface $output
     *
     * @return Configuration
     */
    protected function getMigrationConfiguration(InputInterface $input, OutputInterface $output)
    {
        if ( ! $this->configuration) {
            $outputWriter = new OutputWriter(function($message) use ($output) {
                return $output->writeln($message);
            });

            $configuration = new YamlConfiguration($outputWriter);
            $configuration->load($input->getOption('configuration'));

            $this->setMigrationConfiguration($configuration);
        }

        return $this->configuration;
    }

    public function getSchemaPath(Configuration $configuration, $file)
    {
        $dir = $configuration->getSchemaDirectory();
        $dir = $dir ? $dir : getcwd();
        $dir = rtrim($dir, '/');
        if (!realpath($dir)) {
            mkdir($dir, 0777, true);
        }
        return $dir . '/' . $file;
    }
}
