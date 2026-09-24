<?php

declare(strict_types=1);

namespace SqlSemantics\Serialization\Definition\MySqlTable;

use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\MySqlTable\Table\TableOptionReset;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Sql\Build;
use SqlSemantics\Model\Sql\Tree;
use SqlSemantics\Schema\Table\MySqlProperties;

/**
 * Writes the MySQL table options that follow the classic storage options, the partitioning last.
 * @visibility SqlSemantics
 */
final class TableOptionWriter
{
    /**
     * Writes storage placement, secondary engine, MERGE options, START TRANSACTION, AUTOEXTEND_SIZE, and partitioning.
     */
    public static function write(MySqlProperties $properties): Tree
    {
        $parts = [];
        if ($properties->storage !== null) {
            $parts[] = Build::keyword('STORAGE ' . $properties->storage->value);
        }
        if ($properties->secondaryEngine !== null) {
            array_push($parts, Build::keyword('SECONDARY_ENGINE ='), Build::identifier([$properties->secondaryEngine], Dialect::MySql));
        }
        if ($properties->insertMethod !== null) {
            $parts[] = Build::keyword('INSERT_METHOD = ' . $properties->insertMethod->value);
        }
        if ($properties->union !== null) {
            array_push($parts, Build::keyword('UNION ='), Build::parentheses(Build::separated(array_map(static fn (QualifiedName $table): Tree => Build::identifier($table->parts, Dialect::MySql), $properties->union))));
        }
        if ($properties->autoextendSize !== null) {
            $parts[] = Build::keyword('AUTOEXTEND_SIZE = ' . $properties->autoextendSize);
        }
        if ($properties->startTransaction) {
            $parts[] = Build::keyword('START TRANSACTION');
        }
        if ($properties->partitioning !== null) {
            $parts[] = Partitionings::write($properties->partitioning);
        }
        return new Tree('mysql-table-options', $parts);
    }

    /**
     * Writes options returned to their defaults.
     * @param list<TableOptionReset> $resets
     */
    public static function resets(array $resets): Tree
    {
        return new Tree('table-option-resets', array_map(static fn (TableOptionReset $reset): Tree => Build::keyword($reset->value . ' = ' . ($reset === TableOptionReset::SecondaryEngine ? 'NULL' : 'DEFAULT')), $resets));
    }
}
