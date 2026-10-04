<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Server\Maintenance;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\MySql\Rules\Server\AdminRows;
use SqlSemantics\Platform\MySql\Rules\Server\Histograms;
use SqlSemantics\Platform\MySql\Rules\Server\MaintainedTables;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Statement;

/**
 * `ANALYZE [NO_WRITE_TO_BINLOG | LOCAL] TABLE t, … [histogram]`: a request to update the key distribution statistics of tables, or their histograms.
 *
 * Mirrors PT_analyze_table_stmt (Sql_cmd_analyze_table). Rule: MYSQL-ANALYZE-TABLE-001. Each table resolves by
 * MYSQL-SERVER-TABLES-001 and its resolution is the relation fact of its
 * MaintainedTable; a table named twice is NonUniqueTable. A histogram request is checked against the one table by MYSQL-HISTOGRAM-001. LOCAL is a synonym of NO_WRITE_TO_BINLOG, which is written. TABLE and
 * TABLES are synonyms; TABLE is written. The server returns one row per
 * table and message; its columns are MYSQL-ADMIN-ROWS-001.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/analyze-table.html.
 * Status: Implemented.
 *
 * @visibility public
 * @example Reading the request
 *     $statement = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('analyze local tables t, shop.u');
 *     [$statement->toString(), $statement->statement->noWriteToBinlog] // => ['ANALYZE NO_WRITE_TO_BINLOG TABLE t, shop.u', true]
 */
final class AnalyzeTable implements Statement
{
    use Snapshot;

    /**
     * @var list<MaintainedTable> The tables in written order; at least one
     */
    public readonly array $tables;

    /**
     * @param bool $noWriteToBinlog Whether NO_WRITE_TO_BINLOG or its synonym LOCAL is written
     * @param list<MaintainedTable> $tables The tables in written order; at least one
     * @param Histogram|null $histogram The histogram request, when written (MySQL 8.0 and later)
     */
    public function __construct(public readonly bool $noWriteToBinlog, array $tables, public readonly ?Histogram $histogram = null)
    {
        $this->tables = (new MaintainedTables())->checked($tables);

    }

    /**
     * Records the resolution of each table, reports a table named twice, and records the result rows.
     */
    public function deriveStatement(Derivation $derivation): void
    {
        $facts = (new MaintainedTables())->derive($derivation, $this->tables);
        if ($this->histogram !== null) {
            (new Histograms())->derive($derivation, $this->histogram, $facts);
        }
        (new AdminRows())->admin($derivation);
    }

    /**
     * Writes the request.
     */
    public function render(Output $out): void
    {
        (new MaintainedTables())->render($out, ['ANALYZE', 'TABLE'], $this->noWriteToBinlog, $this->tables);
        $out->node($this->histogram);
    }
}
