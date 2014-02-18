<?php

namespace Yam\Migrations;

class MigrationException extends \Exception
{
    public static function migrationsNamespaceRequired()
    {
        return new self('Migrations namespace must be configured in order to use Doctrine migrations.', 2);
    }

    public static function migrationsDirectoryRequired()
    {
        return new self('Migrations directory must be configured in order to use Doctrine migrations.', 3);
    }

    public static function noMigrationsToExecute()
    {
        return new self('Could not find any migrations to execute.', 4);
    }

    public static function unknownMigrationVersion($version)
    {
        return new self(sprintf('Could not find migration version %s', $version), 5);
    }

    public static function alreadyAtVersion($version)
    {
        return new self(sprintf('Database is already at version %s', $version), 6);
    }

    public static function duplicateMigrationVersion($version, $class)
    {
        return new self(sprintf('Migration version %s already registered with class %s', $version, $class), 7);
    }

    public static function configurationFileAlreadyLoaded()
    {
        return new self(sprintf('Migrations configuration file already loaded'), 8);
    }
}
