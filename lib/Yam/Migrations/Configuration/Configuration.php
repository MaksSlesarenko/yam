<?php

namespace Yam\Migrations\Configuration;

use Doctrine\DBAL\Connection;
use Yam\Migrations\Exception\MigrationException;
use Yam\Migrations\OutputWriter;
use Yam\Migrations\Version;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Type;

class Configuration
{
    /**
     * Name of this set of migrations
     *
     * @var string
     */
    private $name;

    /**
     * Flag for whether or not the migration table has been created
     *
     * @var boolean
     */
    private $migrationTableCreated = false;

    /**
     * Connection instance to use for migrations
     *
     * @var Connection
     */
    private $connection;

    /**
     * OutputWriter instance for writing output during migrations
     *
     * @var OutputWriter
     */
    private $outputWriter;

    /**
     * The migration table name to track versions in
     *
     * @var string
     */
    private $migrationsTableName = 'yam_migration_versions';

    /**
     * The path to a directory where new migration classes will be written
     *
     * @var string
     */
    private $directory;

    /**
     *
     * The path to a directory where new schema files will be written
     *
     * @var string
     */
    private $schemaDirectory;

    /**
     *
     * The path to a directory where new data files will be written
     *
     * @var string
     */
    private $dataDirectory;

    /**
     * Namespace the migration classes live in
     *
     * @var string
     */
    private $migrationsNamespace;

    /**
     * Array of the registered migrations
     *
     * @var Version[]
     */
    private $migrations = array();

    /**
     * Construct a migration configuration object.
     *
     * @param OutputWriter $outputWriter A OutputWriter instance
     */
    public function __construct(OutputWriter $outputWriter = null)
    {
        if ($outputWriter === null) {
            $outputWriter = new OutputWriter();
        }
        $this->outputWriter = $outputWriter;
    }

    /**
     * Validation that this instance has all the required properties configured
     *
     * @throws MigrationException
     */
    public function validate()
    {
        if ( ! $this->migrationsNamespace) {
            throw MigrationException::migrationsNamespaceRequired();
        }
        if ( ! $this->directory) {
            throw MigrationException::migrationsDirectoryRequired();
        }
    }

    /**
     * Set the name of this set of migrations
     *
     * @param string $name The name of this set of migrations
     */
    public function setName($name)
    {
        $this->name = $name;
    }

    /**
     * Returns the name of this set of migrations
     *
     * @return string $name The name of this set of migrations
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Returns the OutputWriter instance
     *
     * @return OutputWriter $outputWriter  The OutputWriter instance
     */
    public function getOutputWriter()
    {
        return $this->outputWriter;
    }

    /**
     * Returns a timestamp version as a formatted date
     *
     * @param string $version
     *
     * @return string The formatted version
     */
    public function formatVersion($version)
    {
        return sprintf('%s-%s-%s %s:%s:%s',
            substr($version, 0, 4),
            substr($version, 4, 2),
            substr($version, 6, 2),
            substr($version, 8, 2),
            substr($version, 10, 2),
            substr($version, 12, 2)
        );
    }

    /**
     * Returns the Connection instance
     *
     * @return Connection $connection  The Connection instance
     */
    public function getConnection()
    {
        return $this->connection;
    }

    /**
     * @param Connection $connection.
     */
    public function setConnection(Connection $connection)
    {
        $this->connection = $connection;
    }

    /**
     * Set the migration table name
     *
     * @param string $tableName The migration table name
     */
    public function setMigrationsTableName($tableName)
    {
        $this->migrationsTableName = $tableName;
    }

    /**
     * Returns the migration table name
     *
     * @return string $migrationsTableName The migration table name
     */
    public function getMigrationsTableName()
    {
        return $this->migrationsTableName;
    }

    /**
     * Set the main directory where new migration classes are generated
     *
     * @param string $directory The main directory
     */
    public function setDirectory($directory)
    {
        $this->directory = $directory;
    }

    /**
     * Returns the new migrations directory where new migration classes are generated
     *
     * @return string $directory The new migrations directory
     */
    public function getMigrationsDirectory()
    {
        return $this->directory . '/version';
    }

    /**
     * Returns the new schema directory where new schema configuration files classes are generated
     *
     * @return string $schemaDirectory The new migrations directory
     */
    public function getSchemaDirectory()
    {
        return $this->directory . '/schema';
    }

    /**
     * Returns the new data directory where new data configuration files classes are generated
     *
     * @return string $dataDirectory The new data directory
     */
    public function getDataDirectory()
    {
        return $this->directory . '/data';
    }

    /**
     * Set the migrations namespace
     *
     * @param string $migrationsNamespace The migrations namespace
     */
    public function setMigrationsNamespace($migrationsNamespace)
    {
        $this->migrationsNamespace = $migrationsNamespace;
    }

    /**
     * Returns the migrations namespace
     *
     * @return string $migrationsNamespace The migrations namespace
     */
    public function getMigrationsNamespace()
    {
        return $this->migrationsNamespace;
    }

    /**
     * Register migrations from a given directory. Recursively finds all files
     * with the pattern VersionYYYYMMDDHHMMSS.php as the filename and registers
     * them as migrations.
     *
     * @param string $path The root directory to where some migration classes live.
     *
     * @return Version[] The array of migrations registered.
     */
    public function registerMigrationsFromDirectory($path)
    {
        $path = realpath($path);
        $path = rtrim($path, '/');
        $files = glob($path . '/Version*.php');
        //$files = glob($path . '/*/version/Version*.php');
        $versions = array();

        if ($files) {
            foreach ($files as $file) {

                //$dir = basename(dirname(dirname($file)));

                require_once($file);
                $info = pathinfo($file);
                $version = substr($info['filename'], 7);
                $class = $this->migrationsNamespace . '\\' . $info['filename'];
                $versions[] = $this->registerMigration($version, $class);
            }
        }

        return $versions;
    }

    /**
     * Register a single migration version to be executed by a AbstractMigration
     * class.
     *
     * @param string $version The version of the migration in the format YYYYMMDDHHMMSS.
     * @param string $class   The migration class to execute for the version.
     *
     * @return Version
     *
     * @throws MigrationException
     */
    public function registerMigration($version, $class)
    {
        $version = (string) $version;
        $class = (string) $class;
        if (isset($this->migrations[$version])) {
            throw MigrationException::duplicateMigrationVersion($version, get_class($this->migrations[$version]));
        }
        $version = new Version($this, $version, $class);
        $this->migrations[$version->getVersion()] = $version;
        ksort($this->migrations);

        return $version;
    }

    /**
     * Register an array of migrations. Each key of the array is the version and
     * the value is the migration class name.
     *
     *
     * @param array $migrations
     *
     * @return Version[]
     */
    public function registerMigrations(array $migrations)
    {
        $versions = array();
        foreach ($migrations as $version => $class) {
            $versions[] = $this->registerMigration($version, $class);
        }

        return $versions;
    }

    /**
     * Get the array of registered migration versions.
     *
     * @return Version[] $migrations
     */
    public function getMigrations()
    {
        return $this->migrations;
    }

    /**
     * Returns the Version instance for a given version in the format YYYYMMDDHHMMSS.
     *
     * @param string $version The version string in the format YYYYMMDDHHMMSS.
     *
     * @return Version
     *
     * @throws MigrationException Throws exception if migration version does not exist.
     */
    public function getVersion($version)
    {
        if ( ! isset($this->migrations[$version])) {
            throw MigrationException::unknownMigrationVersion($version);
        }

        return $this->migrations[$version];
    }

    /**
     * Check if a version exists.
     *
     * @param string $version
     *
     * @return boolean
     */
    public function hasVersion($version)
    {
        return isset($this->migrations[$version]);
    }

    /**
     * Check if a version has been migrated or not yet
     *
     * @param Version $version
     *
     * @return boolean
     */
    public function hasVersionMigrated(Version $version)
    {
        $this->createMigrationTable();

        $version = $this->connection->fetchColumn("SELECT version FROM " . $this->migrationsTableName . " WHERE version = ?", array($version->getVersion()));

        return $version !== false;
    }

    /**
     * Returns all migrated versions from the versions table, in an array.
     *
     * @return Version[]
     */
    public function getMigratedVersions()
    {
        $this->createMigrationTable();

        $ret = $this->connection->fetchAll("SELECT version FROM " . $this->migrationsTableName);
        $versions = array();
        foreach ($ret as $version) {
            $versions[] = current($version);
        }

        return $versions;
    }

    /**
     * Returns an array of available migration version numbers.
     *
     * @return array
     */
    public function getAvailableVersions()
    {
        $availableVersions = array();
        foreach ($this->migrations as $migration) {
            $availableVersions[] = $migration->getVersion();
        }

        return $availableVersions;
    }

    /**
     * Returns the current migrated version from the versions table.
     *
     * @return string
     */
    public function getCurrentVersion()
    {
        $this->createMigrationTable();

        $where = null;
        if ($this->migrations) {
            $migratedVersions = array();
            foreach ($this->migrations as $migration) {
                $migratedVersions[] = sprintf("'%s'", $migration->getVersion());
            }
            $where = " WHERE version IN (" . implode(', ', $migratedVersions) . ")";
        }

        $sql = sprintf("SELECT version FROM %s%s ORDER BY version DESC",
            $this->migrationsTableName, $where
        );

        $sql = $this->connection->getDatabasePlatform()->modifyLimitQuery($sql, 1);
        $result = $this->connection->fetchColumn($sql);

        return $result !== false ? (string) $result : '0';
    }

    /**
     * Returns the total number of executed migration versions
     *
     * @return integer
     */
    public function getNumberOfExecutedMigrations()
    {
        $this->createMigrationTable();

        $result = $this->connection->fetchColumn("SELECT COUNT(version) FROM " . $this->migrationsTableName);

        return $result !== false ? $result : 0;
    }

    /**
     * Returns the total number of available migration versions
     *
     * @return integer
     */
    public function getNumberOfAvailableMigrations()
    {
        return count($this->migrations);
    }

    /**
     * Returns the latest available migration version.
     *
     * @return string The version string in the format YYYYMMDDHHMMSS.
     */
    public function getLatestVersion()
    {
        $versions = array_keys($this->migrations);
        $latest = end($versions);

        return $latest !== false ? (string) $latest : '0';
    }

    /**
     * Create the migration table to track migrations with.
     *
     * @return boolean Whether or not the table was created.
     */
    public function createMigrationTable()
    {
        $this->validate();

        if ($this->migrationTableCreated) {
            return false;
        }

        if ( ! $this->connection->getSchemaManager()->tablesExist(array($this->migrationsTableName))) {
            $columns = array(
                'version' => new Column('version', Type::getType('string'), array('length' => 255)),
            );
            $table = new Table($this->migrationsTableName, $columns);
            $table->setPrimaryKey(array('version'));
            $this->connection->getSchemaManager()->createTable($table);

            $this->migrationTableCreated = true;

            return true;
        }

        return false;
    }

    /**
     * Returns the array of migrations to executed based on the given direction
     * and target version number.
     *
     * @param string $direction The direction we are migrating.
     * @param string $to        The version to migrate to.
     *
     * @return Version[] $migrations   The array of migrations we can execute.
     */
    public function getMigrationsToExecute($direction, $to)
    {
        if ($direction === 'down') {
            if (count($this->migrations)) {
                $allVersions = array_reverse(array_keys($this->migrations));
                $classes = array_reverse(array_values($this->migrations));
                $allVersions = array_combine($allVersions, $classes);
            } else {
                $allVersions = array();
            }
        } else {
            $allVersions = $this->migrations;
        }
        $versions = array();
        $migrated = $this->getMigratedVersions();
        foreach ($allVersions as $version) {
            if ($this->shouldExecuteMigration($direction, $version, $to, $migrated)) {
                $versions[$version->getVersion()] = $version;
            }
        }

        return $versions;
    }

    /**
     * Check if we should execute a migration for a given direction and target
     * migration version.
     *
     * @param string  $direction The direction we are migrating.
     * @param Version $version   The Version instance to check.
     * @param string  $to        The version we are migrating to.
     * @param array   $migrated  Migrated versions array.
     *
     * @return boolean
     */
    private function shouldExecuteMigration($direction, Version $version, $to, $migrated)
    {
        if ($direction === 'down') {
            if ( ! in_array($version->getVersion(), $migrated)) {
                return false;
            }

            return $version->getVersion() > $to;
        }

        if ($direction === 'up') {
            if (in_array($version->getVersion(), $migrated)) {
                return false;
            }

            return $version->getVersion() <= $to;
        }
    }
}
