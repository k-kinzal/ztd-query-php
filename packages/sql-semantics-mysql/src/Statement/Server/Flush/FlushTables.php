<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Server\Flush;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Diagnostic\InvalidConstruction;
use SqlSemantics\Platform\MySql\Rules\Server\MaintainedTables;
use SqlSemantics\Platform\MySql\Statement\Server\Maintenance\MaintainedTable;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Statement;

/**
 * `FLUSH [NO_WRITE_TO_BINLOG | LOCAL] TABLES [t, …] [WITH READ LOCK | FOR EXPORT]`: a request to close open tables.
 *
 * Mirrors SQLCOM_FLUSH with REFRESH_TABLES. Rule: MYSQL-FLUSH-TABLES-001.
 * Without a list every open table is closed. Each table resolves by
 * MYSQL-SERVER-TABLES-001 and its resolution is the relation fact of its
 * MaintainedTable; a table named twice is NonUniqueTable. FOR EXPORT
 * without a table list is a syntax error of the server and cannot be
 * constructed. TABLE and TABLES are synonyms; TABLE is written.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/flush.html.
 * Status: Implemented.
 *
 * @visibility public
 * @example Flushing tables for export
 *     $flush = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('flush tables t, u for export');
 *     [$flush->toString(), $flush->statement->lock] // => ['FLUSH TABLE t, u FOR EXPORT', \SqlSemantics\Platform\MySql\Statement\Server\Flush\FlushLock::ForExport]
 */
final class FlushTables implements Statement
{
    use Snapshot;

    /**
     * @var list<MaintainedTable> The tables in written order; empty for every open table
     */
    public readonly array $tables;

    /**
     * @param bool $noWriteToBinlog Whether NO_WRITE_TO_BINLOG or its synonym LOCAL is written
     * @param list<MaintainedTable> $tables The tables in written order; empty for every open table
     * @param FlushLock|null $lock WITH READ LOCK or FOR EXPORT, when written
     * @throws InvalidConstruction When FOR EXPORT has no table list
     */
    public function __construct(public readonly bool $noWriteToBinlog, array $tables = [], public readonly ?FlushLock $lock = null)
    {
        $this->tables = Check::listOf($tables, MaintainedTable::class, 'FLUSH TABLES takes a list of tables.');
        Check::input($lock !== FlushLock::ForExport || $this->tables !== [], 'FLUSH TABLES ... FOR EXPORT names at least one table.');
    }

    /**
     * Records the resolution of each table and reports a table named twice.
     */
    public function deriveStatement(Derivation $derivation): void
    {
        (new MaintainedTables())->derive($derivation, $this->tables);
    }

    /**
     * Writes the request.
     */
    public function render(Output $out): void
    {
        (new MaintainedTables())->render($out, ['FLUSH', 'TABLE'], $this->noWriteToBinlog, $this->tables);
        if ($this->lock !== null) {
            $out->keyword(...explode(' ', $this->lock->value));
        }
    }
}
