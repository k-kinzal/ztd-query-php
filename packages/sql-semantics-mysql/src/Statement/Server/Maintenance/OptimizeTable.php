<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Server\Maintenance;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\MySql\Rules\Server\AdminRows;
use SqlSemantics\Platform\MySql\Rules\Server\MaintainedTables;
use SqlSemantics\Platform\MySql\Statement\Statement;
use SqlSemantics\Rendering\Output;

/**
 * `OPTIMIZE [NO_WRITE_TO_BINLOG | LOCAL] TABLE t, …`: a request to reorganize the storage of tables.
 *
 * Mirrors PT_optimize_table_stmt (Sql_cmd_optimize_table). Rule: MYSQL-OPTIMIZE-TABLE-001. Each table resolves by
 * MYSQL-SERVER-TABLES-001 and its resolution is the relation fact of its
 * MaintainedTable; a table named twice is NonUniqueTable. LOCAL and NO_WRITE_TO_BINLOG suppress binary logging; their keyword forms are retained. TABLE and
 * TABLES are synonyms; the written keyword is retained. The server returns one row per
 * table and message; its columns are MYSQL-ADMIN-ROWS-001.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/optimize-table.html.
 * Status: Implemented.
 *
 * @visibility public
 * @example Reading the request
 *     $statement = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('optimize table t');
 *     [$statement->toString(), $statement->binlog === null] // => ['OPTIMIZE TABLE t', true]
 */
final class OptimizeTable extends \SqlSemantics\Platform\MySql\Statement\SqlStatement implements Statement, \SqlSemantics\Statement\AnalyzedStatement
{
    use \SqlSemantics\Statement\Semantics\Occurrence;

    /**
     * @var list<MaintainedTable> The tables in written order; at least one
     */
    public readonly array $tables;

    /**
     * @param ?\SqlSemantics\Platform\MySql\Statement\Server\BinlogOption $binlog The binary logging suppression keyword, or null when absent
     * @param list<MaintainedTable> $tables The tables in written order; at least one
     */
    public function __construct(public readonly ?\SqlSemantics\Platform\MySql\Statement\Server\BinlogOption $binlog, array $tables, ?\SqlSemantics\Platform\MySql\Statement\StatementSemantics $statementInformation = null, public readonly ?\SqlSemantics\Statement\Source\Span $inputSource = null, public readonly \SqlSemantics\Platform\MySql\Statement\Table\TableWord $tableWord = \SqlSemantics\Platform\MySql\Statement\Table\TableWord::Table)
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
        (new MaintainedTables())->derive($derivation, $this->tables);
        (new AdminRows())->admin($derivation);
    }

    /**
     * Writes the request.
     */
    public function render(Output $out): void
    {
        (new MaintainedTables())->render($out, ['OPTIMIZE', $this->tableWord->value], $this->binlog, $this->tables);
    }
}
