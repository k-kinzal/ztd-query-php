<?php

declare(strict_types=1);

namespace SqlSemantics\Serialization\Execution;

use SqlSemantics\Model\Locking\MySqlTableLock;
use SqlSemantics\Model\Sql\Build;
use SqlSemantics\Model\Sql\Tree;
use SqlSemantics\Model\Statement\Locking\LockRelationsStatement;
use SqlSemantics\Model\Statement\Locking\LockTablesStatement;
use SqlSemantics\Serialization\Query\Relations;

/**
 * Writes typed table-lock operands without acquiring database locks.
 * @visibility SqlSemantics
 */
final class TableLocks
{
    /**
     * Preserves ordered targets, aliases, conflict modes and wait policy.
     */
    public static function write(LockRelationsStatement|LockTablesStatement $statement): Tree
    {
        $dialect = $statement->origin->dialect;
        if ($statement instanceof LockRelationsStatement) {
            return new Tree('lock_relations', [Build::keyword('LOCK TABLE'), Build::separated(array_map(static fn ($table): Tree => Relations::target($table, $dialect), $statement->tables)), Build::keyword('IN ' . $statement->mode->value . ' MODE'), ...($statement->nowait ? [Build::keyword('NOWAIT')] : [])]);
        }
        return new Tree('lock_tables', [Build::keyword('LOCK TABLES'), Build::separated(array_map(static fn (MySqlTableLock $lock): Tree => new Tree('table_lock', [Relations::write($lock->table, $dialect), Build::keyword($lock->mode->value)]), $statement->locks))]);
    }
}
