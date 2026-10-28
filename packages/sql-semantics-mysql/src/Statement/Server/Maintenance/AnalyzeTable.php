<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Server\Maintenance;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\MySql\Rules\Server\AdminRows;
use SqlSemantics\Platform\MySql\Rules\Server\Histograms;
use SqlSemantics\Platform\MySql\Rules\Server\MaintainedTables;
use SqlSemantics\Platform\MySql\Statement\Statement;
use SqlSemantics\Rendering\Output;

/**
 * `ANALYZE [NO_WRITE_TO_BINLOG | LOCAL] TABLE t, … [histogram]`: a request to update the key distribution statistics of tables, or their histograms.
 *
 * Mirrors PT_analyze_table_stmt (Sql_cmd_analyze_table). Rule: MYSQL-ANALYZE-TABLE-001. Each table resolves by
 * MYSQL-SERVER-TABLES-001 and its resolution is the relation fact of its
 * MaintainedTable; a table named twice is NonUniqueTable. A histogram request is checked against the one table by MYSQL-HISTOGRAM-001. LOCAL and NO_WRITE_TO_BINLOG suppress binary logging; their keyword forms are retained. TABLE and
 * TABLES are synonyms; the written keyword is retained. The server returns one row per
 * table and message; its columns are MYSQL-ADMIN-ROWS-001.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/analyze-table.html.
 * Status: Implemented.
 *
 * @visibility public
 * @example Reading the request
 *     $statement = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('analyze local tables t, shop.u');
 *     [$statement->toString(), $statement->binlog !== null] // => ['ANALYZE LOCAL TABLES t, shop.u', true]
 */
final class AnalyzeTable extends \SqlSemantics\Platform\MySql\Statement\SqlStatement implements Statement, \SqlSemantics\Statement\AnalyzedStatement
{
    use \SqlSemantics\Statement\Semantics\Occurrence;

    /**
     * @var list<MaintainedTable> The tables in written order; at least one
     */
    public readonly array $tables;

    /**
     * @param ?\SqlSemantics\Platform\MySql\Statement\Server\BinlogOption $binlog The binary logging suppression keyword, or null when absent
     * @param list<MaintainedTable> $tables The tables in written order; at least one
     * @param Histogram|null $histogram The histogram request, when written (MySQL 8.0 and later)
     */
    public function __construct(public readonly ?\SqlSemantics\Platform\MySql\Statement\Server\BinlogOption $binlog, array $tables, public readonly ?Histogram $histogram = null, ?\SqlSemantics\Platform\MySql\Statement\StatementSemantics $statementInformation = null, public readonly ?\SqlSemantics\Statement\Source\Span $inputSource = null, public readonly \SqlSemantics\Platform\MySql\Statement\Table\TableWord $tableWord = \SqlSemantics\Platform\MySql\Statement\Table\TableWord::Table)
    {
        if ($statementInformation !== null) {
            $this->initializeStatementInformation($statementInformation);
        }
        $this->tables = (new MaintainedTables())->checked($tables);

        (new \SqlSemantics\Platform\MySql\Rules\Query\Structure\Generations())->check($this);
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
        (new MaintainedTables())->render($out, ['ANALYZE', $this->tableWord->value], $this->binlog, $this->tables);
        $out->node($this->histogram);
    }
}
