<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Server\Lock;

use SqlSemantics\Platform\MySql\Model\Construction\Derivation;
use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Model\Diagnostic\InvalidConstruction;
use SqlSemantics\Platform\MySql\Model\Rendering\Output;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;
use SqlSemantics\Platform\MySql\Model\Statement\Statement;
use SqlSemantics\Platform\MySql\Rules\Server\TableNames;

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
 * @visibility SqlSemantics
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
