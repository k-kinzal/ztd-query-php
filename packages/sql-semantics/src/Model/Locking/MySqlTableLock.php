<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Locking;

use SqlSemantics\Dialect;
use SqlSemantics\Model\Relation\TableReference;
use SqlSemantics\Model\Validation\StatementOperands;

/**
 * One named MySQL table occurrence and its required session access mode.
 * @visibility public
 * @example Inspecting a lock's alias and mode
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build('CREATE TABLE t(id INTEGER)');
 *     $statement = (new \SqlSemantics\Binder($schema))->bind('LOCK TABLES t AS a READ LOCAL');
 *     $statement->locks[0]->mode === \SqlSemantics\Model\Locking\MySqlLockMode::ReadLocal // => true
 *     $statement->locks[0]->table->alias // => 'a'
 */
final class MySqlTableLock
{
    /**
     * Rejects table declarations from another dialect.
     */
    public function __construct(public readonly TableReference $table, public readonly MySqlLockMode $mode)
    {
        StatementOperands::relation($table, Dialect::MySql);
    }
}
