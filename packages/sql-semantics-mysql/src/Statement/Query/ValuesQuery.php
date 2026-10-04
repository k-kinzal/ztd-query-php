<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Query;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Rules\Query\ResultSlots;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Fact\QueryFact;
use SqlSemantics\Statement\Query;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Statement;

/**
 * The VALUES statement: a table of rows written with row constructors (MySQL 8.0.19 and later).
 *
 * Rule: MYSQL-VALUES-001. The columns are named column_0, column_1 and so
 * on; the type of a column is aggregated over the rows
 * (MYSQL-RESULT-SLOTS-001) and it can be NULL when a value of it can. Rows of
 * different lengths are reported. The values see no column of the query.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/values.html. Status: Implemented.
 *
 * @visibility public
 * @example Reading the rows of VALUES
 *     $values = new \SqlSemantics\Platform\MySql\Statement\Query\ValuesQuery([new \SqlSemantics\Platform\MySql\Statement\Query\ValueRow([new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('1')])]);
 *     count($values->rows) // => 1
 * @example Refusing a VALUES statement without rows
 *     new \SqlSemantics\Platform\MySql\Statement\Query\ValuesQuery([]) // throws \SqlSemantics\Diagnostic\InvalidConstruction
 */
final class ValuesQuery implements Statement, Query
{
    use Snapshot;

    /**
     * @var non-empty-list<ValueRow> The rows in written order
     */
    public readonly array $rows;

    /**
     * @param list<ValueRow> $rows The rows in written order; at least one
     */
    public function __construct(array $rows)
    {
        $this->rows = Check::listOf($rows, ValueRow::class, 'VALUES holds at least one row.', 1);
    }

    /**
     * Derives the statement as a statement root and records its rows as the output.
     */
    public function deriveStatement(Derivation $derivation): void
    {
        $derivation->output($derivation->query($this, $derivation->environment()));
    }

    /**
     * Derives every value and the output columns.
     */
    public function deriveQuery(Derivation $derivation, Environment $outer): QueryFact
    {
        return (new ResultSlots())->values($this, $derivation, $outer);
    }

    /**
     * Writes the rows.
     */
    public function render(Output $out): void
    {
        $out->keyword('VALUES')->list($this->rows);
    }
}
