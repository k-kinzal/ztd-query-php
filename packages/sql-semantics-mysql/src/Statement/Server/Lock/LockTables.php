<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Server\Lock;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Diagnostic\InvalidConstruction;
use SqlSemantics\Platform\MySql\Rules\Server\TableNames;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Statement;

/**
 * `LOCK {TABLE | TABLES} t [[AS] alias] lock_type, …`: a request to lock tables for the session.
 *
 * Mirrors SQLCOM_LOCK_TABLES. Rule: MYSQL-LOCK-TABLES-001. Each table name
 * resolves by MYSQL-SERVER-TABLES-001 and its resolution is the relation
 * fact of its TableLock; an alias or name used twice is NonUniqueTable. The
 * statement releases the locks the session held before. TABLE and TABLES
 * are synonyms; TABLES is written.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/lock-tables.html.
 * Status: Implemented.
 *
 * @visibility public
 * @example Locking two tables
 *     $lock = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('lock table t read local, u as v write');
 *     [$lock->toString(), $lock->statement->locks[1]->alias?->value] // => ['LOCK TABLES t READ LOCAL, u AS v WRITE', 'v']
 */
final class LockTables implements Statement
{
    use Snapshot;

    /**
     * @var list<TableLock> The locked tables in written order; at least one
     */
    public readonly array $locks;

    /**
     * @param list<TableLock> $locks The locked tables in written order; at least one
     * @throws InvalidConstruction When the list is empty
     */
    public function __construct(array $locks)
    {
        $this->locks = Check::listOf($locks, TableLock::class, 'LOCK TABLES locks at least one table.', 1);
    }

    /**
     * Records the resolution of each table and reports a repeated alias.
     */
    public function deriveStatement(Derivation $derivation): void
    {
        $tables = [];
        foreach ($this->locks as $lock) {
            $tables[] = [$lock, $lock->table, $lock->alias];
        }
        (new TableNames())->record($derivation, $tables);
    }

    /**
     * Writes the request.
     */
    public function render(Output $out): void
    {
        $out->keyword('LOCK', 'TABLES')->list($this->locks);
    }
}
