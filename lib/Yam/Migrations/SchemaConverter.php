<?php

namespace Yam\Migrations;

use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\ForeignKeyConstraint;
use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\SchemaConfig;
use Doctrine\DBAL\Schema\Sequence;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Type;

class SchemaConverter
{
    public static function toSchema(array $data, SchemaConfig $schemaConfig=null)
    {
        if ($schemaConfig == null) {
            $schemaConfig = new SchemaConfig();
        }
        $tables = array();
        foreach ($data['tables'] as $tableName => $tableArray) {
            $columns = array();
            foreach ($tableArray['columns'] as $column) {
                $type = Type::getType($column['type']);
                unset($column['type']);
                $columns[] = new Column($column['name'], $type, $column);
            }
            $foreignKeys = array();
            foreach ($tableArray['foreignKeys'] as $name => $fKey) {
                $foreignKeys[] = new ForeignKeyConstraint(
                    $fKey['columns'],
                    $fKey['foreignTable'],
                    $fKey['foreignColumns'],
                    $name,
                    $fKey['options']
                );
            }
            $indexes = array();
            foreach ($tableArray['indexes'] as $name => $index) {
                $indexes[] = new Index($name, $index['columns'], $index['unique'], $index['primary'], $index['flags']);

            }
            $tables[$tableName] = new Table($tableName, $columns, $indexes, $foreignKeys);
        }

        $sequences = array();
        foreach ($data['sequences'] as $name => $sequenceArray) {
            $sequences[] = new Sequence($name, $sequenceArray['allocation_size'], $sequenceArray['initial_size']);
        }


        $schema = new Schema($tables, $sequences, $schemaConfig);

//        if ($schemaConfig == null) {
//            $schemaConfig = new SchemaConfig();
//        }
//
//        $tables = array();
//        foreach ($data['tables'] as $tableName => $tableArray) {
//            $columns = array();
//            foreach ($tableArray['columns'] as $name => $column) {
//                $type = Type::getType($column['type']);
//                unset($column['type']);
//                $columns[] = new Column($name, $type, $column);
//            }
//            $tables[$tableName] = new Table($tableName, $columns, array(), array());
//        }
//
//
//
//        $sequences = array();
//        foreach ($data['sequences'] as $name => $sequenceArray) {
//            $sequences[] = new Sequence($name, $sequenceArray['allocation_size'], $sequenceArray['initial_size']);
//        }
//
//        $schema = new Schema($tables, $sequences, $schemaConfig);
//
//
//        foreach ($data['tables'] as $tableName => $tableArray) {
//            foreach ($tableArray['foreignKeys'] as $fKey) {
//                $schema->getTable($tableName)->addForeignKeyConstraint(
//                    $fKey['foreignTable'],
//                    $fKey['columns'],
//                    $fKey['foreignColumns']
//                );
//            }
////            foreach ($tableArray['indexes'] as $name => $index) {
////                if ($index['unique']) {
////                    $schema->getTable($tableName)->addUniqueIndex($index['columns'], $name);
////                } else {
////                    $schema->getTable($tableName)->addIndex($index['columns'], $name);
////                }
////
////            }
//        }

        return $schema;
    }

    public static function toArray(AbstractSchemaManager $schemaManager)
    {
        $tables = array();
        foreach ($schemaManager->listTables() as $table) {
            $tables[$table->getName()] = array(
                'columns' => array(),
                'foreignKeys' => array(),
                'indexes' => array(),
            );
            foreach ($table->getColumns() as $column) {
                $columnConfig = $column->toArray();
                $columnConfig['type'] = strtolower($columnConfig['type']->__toString());

                $tables[$table->getName()]['columns'][] = $columnConfig;
            }
            foreach ($table->getForeignKeys() as $fKey) {
                $tables[$table->getName()]['foreignKeys'][$fKey->getName()] = array(
                    'foreignColumns' => $fKey->getForeignColumns(),
                    'foreignTable'   => $fKey->getForeignTableName(),
                    'columns'        => $fKey->getLocalColumns(),
                    'options'        => $fKey->getOptions()
                );
            }
            foreach ($table->getIndexes() as $index) {
                $tables[$table->getName()]['indexes'][$index->getName()] = array(
                    'columns' => $index->getColumns(),
                    'unique'  => $index->isUnique(),
                    'primary' => $index->isPrimary(),
                    'flags'   => $index->getFlags()
                );
            }
        }
        $sequences = array();
        foreach ($schemaManager->listSequences() as $sequence) {
            $sequences[$sequence->getName()] = array(
                'allocation_size' => $sequence->getAllocationSize(),
                'initial_size'    => $sequence->getInitialValue()
            );
        }
        return array('tables' => $tables, 'sequences' => $sequences);
    }
}
