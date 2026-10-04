<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Server\Maintenance;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\MySql\Rules\Server\AdminRows;
use SqlSemantics\Platform\MySql\Rules\Server\MaintainedTables;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Statement;

/**
 * `OPTIMIZE [NO_WRITE_TO_BINLOG | LOCAL] TABLE t, …`: a request to reorganize the storage of tables.
 *
 * Mirrors PT_optimize_table_stmt (Sql_cmd_optimize_table). Rule: MYSQL-OPTIMIZE-TABLE-001. Each table resolves by
 * MYSQL-SERVER-TABLES-001 and its resolution is the relation fact of its
 * MaintainedTable; a table named twice is NonUniqueTable. LOCAL is a synonym of NO_WRITE_TO_BINLOG, which is written. TABLE and
 * TABLES are synonyms; TABLE is written. The server returns one row per
 * table and message; its columns are MYSQL-ADMIN-ROWS-001.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/optimize-table.html.
 * Status: Implemented.
 *
 * @visibility public
 * @example Reading the request
 *     $statement = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('optimize table t');
 *     [$statement->toString(), !$statement->statement->noWriteToBinlog] // => ['OPTIMIZE TABLE t', true]
 */
final class OptimizeTable implements Statement
{
    use Snapshot;

    /**
     * @var list<MaintainedTable> The tables in written order; at least one
     */
    public readonly array $tables;

    /**
     * @param bool $noWriteToBinlog Whether NO_WRITE_TO_BINLOG or its synonym LOCAL is written
     * @param list<MaintainedTable> $tables The tables in written order; at least one
     */
    public function __construct(public readonly bool $noWriteToBinlog, array $tables)
    {
        $this->tables = (new MaintainedTables())->checked($tables);

    }

    /**
     * Records the resolution of each table, reports a table named twice, and records the result rows.
     */
    public function deriveStatement(Derivation $derivation): void
    {
        (new MaintainedTables())->derive($derivation, $this->tables);
        (new AdminRows())->admin($derivation);
    }

    /**
     * Writes the request.
     */
    public function render(Output $out): void
    {
        (new MaintainedTables())->render($out, ['OPTIMIZE', 'TABLE'], $this->noWriteToBinlog, $this->tables);
    }
}
