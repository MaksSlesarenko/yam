#!/usr/bin/env php
<?php

require_once 'vendor/autoload.php';

$helperSet = new \Symfony\Component\Console\Helper\HelperSet();
$helperSet->set(new \Symfony\Component\Console\Helper\DialogHelper(), 'dialog');

\Yam\Migrations\Command\AbstractCommand::$usePrefix = false;

$cli = new \Symfony\Component\Console\Application('Yam Migrations', \Yam\Migrations\MigrationsVersion::VERSION);
$cli->setCatchExceptions(true);
$cli->setHelperSet($helperSet);
$cli->addCommands(array(
    // Migrations Commands
    new \Yam\Migrations\Command\ExecuteCommand(),
    new \Yam\Migrations\Command\GenerateCommand(),
    new \Yam\Migrations\Command\LatestCommand(),
    new \Yam\Migrations\Command\MigrateCommand(),
    new \Yam\Migrations\Command\StatusCommand(),
    new \Yam\Migrations\Command\VersionCommand(),

    new \Yam\Migrations\Command\DiffCommand(),
    new \Yam\Migrations\Command\SchemaReverseCommand(),
    new \Yam\Migrations\Command\DataInsertCommand(),
    new \Yam\Migrations\Command\DataDumpCommand(),
    new \Yam\Migrations\Command\SequenceUpdateCommand(),
    new \Yam\Migrations\Command\CleanCommand(),
));

$input = file_exists('migrations-input.php')
       ? include('migrations-input.php')
       : null;

$output = file_exists('migrations-output.php')
        ? include('migrations-output.php')
        : null;

$cli->run($input, $output);
