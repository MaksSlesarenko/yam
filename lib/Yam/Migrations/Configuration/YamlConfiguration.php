<?php

namespace Yam\Migrations\Configuration;

use Symfony\Component\Yaml\Yaml;

class YamlConfiguration extends AbstractFileConfiguration
{
    /**
     * @inheritdoc
     */
    protected function doLoad($file)
    {
        $array = Yaml::parse($file);

        $conn = \Doctrine\DBAL\DriverManager::getConnection($array['connection']);

        if (!empty($array['sqlLogger'])) {
            $conn->getConfiguration()->setSQLLogger(new $array['sqlLogger']);
        }
        $this->setConnection($conn);

        if (isset($array['name'])) {
            $this->setName($array['name']);
        }
        if (isset($array['table_name'])) {
            $this->setMigrationsTableName($array['table_name']);
        }
        if (isset($array['migrations_namespace'])) {
            $this->setMigrationsNamespace($array['migrations_namespace']);
        }
        if (isset($array['migrations_directory'])) {
            $migrationsDirectory = $this->getDirectoryRelativeToFile($file, $array['migrations_directory']);
            $this->setMigrationsDirectory($migrationsDirectory);
            $this->registerMigrationsFromDirectory($migrationsDirectory);
        }
        if (isset($array['schema_directory'])) {
            $schemaDirectory = $this->getDirectoryRelativeToFile($file, $array['schema_directory']);
            $this->setSchemaDirectory($schemaDirectory);
        }
        if (isset($array['migrations']) && is_array($array['migrations'])) {
            foreach ($array['migrations'] as $migration) {
                $this->registerMigration($migration['version'], $migration['class']);
            }
        }
    }
}
