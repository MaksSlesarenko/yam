<?php

namespace Yam\Migrations\Configuration;

use Symfony\Component\Yaml\Yaml;
use Yam\Migrations\Exception\MigrationException;

class YamlConfiguration extends AbstractFileConfiguration
{
    /**
     * @inheritdoc
     */
    protected function doLoad($file)
    {
        $array = Yaml::parse(file_get_contents($file));

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
        if (isset($array['bootstrap'])) {
            require $array['bootstrap'];
        }
        if (isset($array['directory'])) {
            if (empty($array['directory']) || !is_dir($array['directory'])) {
                throw MigrationException::migrationsDirectoryRequired();
            }

            $directory = $this->getDirectoryRelativeToFile($file, $array['directory']);
            $this->setDirectory($directory);
            $this->registerMigrationsFromDirectory($this->getMigrationsDirectory());
        }
    }
}
