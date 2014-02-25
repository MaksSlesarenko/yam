<?php

namespace Yam\Migrations;

use Yam\Migrations\Configuration\Configuration;
use Doctrine\DBAL\Schema\Schema;
use Yam\Migrations\Exception\AbortMigrationException;
use Yam\Migrations\Exception\IrreversibleMigrationException;
use Yam\Migrations\Exception\SkipMigrationException;

abstract class AbstractMigration
{
    /**
     * The Migrations Configuration instance for this migration
     *
     * @var Configuration
     */
    private $configuration;

    /**
     * The OutputWriter object instance used for outputting information
     *
     * @var OutputWriter
     */
    private $outputWriter;

    /**
     * The Doctrine\DBAL\Connection instance we are migrating
     *
     * @var \Doctrine\DBAL\Connection
     */
    protected $connection;

    /**
     * Reference to the SchemaManager instance referenced by $_connection
     *
     * @var \Doctrine\DBAL\Schema\AbstractSchemaManager
     */
    protected $sm;

    /**
     * Reference to the DatabasePlatform instance referenced by $_connection
     *
     * @var \Doctrine\DBAL\Platforms\AbstractPlatform
     */
    protected $platform;

    /**
     * Reference to the Version instance representing this migration
     *
     * @var Version
     */
    protected $version;

    public function __construct(Version $version)
    {
        $this->configuration = $version->getConfiguration();
        $this->outputWriter = $this->configuration->getOutputWriter();
        $this->connection = $this->configuration->getConnection();
        $this->sm = $this->connection->getSchemaManager();
        $this->platform = $this->connection->getDatabasePlatform();
        $this->version = $version;
    }

    /**
     * Get custom migration name
     *
     * @return string
     */
    public function getName()
    {
    }

    abstract public function up(Schema $schema);
    abstract public function down(Schema $schema);

    protected function addSql($sql, array $params = array(), array $types = array())
    {
        $this->version->addSql($sql, $params, $types);
    }

    protected function write($message)
    {
        $this->outputWriter->write($message);
    }

    protected function throwIrreversibleMigrationException($message = null)
    {
        if ($message === null) {
            $message = 'This migration is irreversible and cannot be reverted.';
        }
        throw new IrreversibleMigrationException($message);
    }

    /**
     * Print a warning message if the condition evaluates to TRUE.
     *
     * @param boolean $condition
     * @param string  $message
     */
    public function warnIf($condition, $message = '')
    {
        $message = (strlen($message)) ? $message : 'Unknown Reason';

        if ($condition === true) {
            $this->outputWriter->write('    <warning>Warning during ' . $this->version->getExecutionState() . ': ' . $message . '</warning>');
        }
    }

    /**
     * Abort the migration if the condition evaluates to TRUE.
     *
     * @param boolean $condition
     * @param string  $message
     *
     * @throws AbortMigrationException
     */
    public function abortIf($condition, $message = '')
    {
        $message = (strlen($message)) ? $message : 'Unknown Reason';

        if ($condition === true) {
            throw new AbortMigrationException($message);
        }
    }

    /**
     * Skip this migration (but not the next ones) if condition evaluates to TRUE.
     *
     * @param boolean $condition
     * @param string  $message
     *
     * @throws SkipMigrationException
     */
    public function skipIf($condition, $message = '')
    {
        $message = (strlen($message)) ? $message : 'Unknown Reason';

        if ($condition === true) {
            throw new SkipMigrationException($message);
        }
    }

    public function preUp(Schema $schema)
    {
    }

    public function postUp(Schema $schema)
    {
    }

    public function preDown(Schema $schema)
    {
    }

    public function postDown(Schema $schema)
    {
    }
}
