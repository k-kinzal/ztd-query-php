<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Server\Maintenance;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\MySql\Rules\Server\AdminRows;
use SqlSemantics\Platform\MySql\Rules\Server\MaintainedTables;
use SqlSemantics\Platform\MySql\Statement\Statement;
use SqlSemantics\Rendering\Output;

/**
 * `CHECKSUM TABLE t, … [QUICK | EXTENDED]`: a request to compute a checksum of the contents of tables.
 *
 * Mirrors SQLCOM_CHECKSUM. Rule: MYSQL-CHECKSUM-TABLE-001. Each table resolves by
 * MYSQL-SERVER-TABLES-001 and its resolution is the relation fact of its
 * MaintainedTable; a table named twice is NonUniqueTable. TABLE and
 * TABLES are synonyms; the written keyword is retained. The server returns one row per
 * table and message; its columns are MYSQL-ADMIN-ROWS-001.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/checksum-table.html.
 * Status: Implemented.
 *
 * @visibility public
 * @example Reading the request
 *     $statement = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('checksum table t extended');
 *     [$statement->toString(), $statement->mode === \SqlSemantics\Platform\MySql\Statement\Server\Maintenance\ChecksumMode::Extended] // => ['CHECKSUM TABLE t EXTENDED', true]
 */
final class ChecksumTable extends \SqlSemantics\Platform\MySql\Statement\SqlStatement implements Statement, \SqlSemantics\Statement\AnalyzedStatement
{
    use \SqlSemantics\Statement\Semantics\Occurrence;

    /**
     * @var list<MaintainedTable> The tables in written order; at least one
     */
    public readonly array $tables;

    /**
     * @param list<MaintainedTable> $tables The tables in written order; at least one
     * @param ChecksumMode|null $mode QUICK or EXTENDED, when written
     */
    public function __construct(array $tables, public readonly ?ChecksumMode $mode = null, ?\SqlSemantics\Platform\MySql\Statement\StatementSemantics $statementInformation = null, public readonly ?\SqlSemantics\Statement\Source\Span $inputSource = null, public readonly \SqlSemantics\Platform\MySql\Statement\Table\TableWord $tableWord = \SqlSemantics\Platform\MySql\Statement\Table\TableWord::Table)
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
        (new AdminRows())->checksum($derivation);
    }

    /**
     * Writes the request.
     */
    public function render(Output $out): void
    {
        (new MaintainedTables())->render($out, ['CHECKSUM', $this->tableWord->value], null, $this->tables);
        if ($this->mode !== null) {
            $out->keyword($this->mode->value);
        }
    }
}
