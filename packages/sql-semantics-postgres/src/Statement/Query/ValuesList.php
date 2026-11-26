<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Query;

use SqlSemantics\Platform\PostgreSql\Model\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Rendering\Output;
use SqlSemantics\Platform\PostgreSql\Model\Resolution\Environment;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Fact\QueryFact;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Query;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Statement;
use SqlSemantics\Platform\PostgreSql\Rules\Query\Facts\QueryRoots;
use SqlSemantics\Platform\PostgreSql\Rules\Query\Facts\ValuesFacts;
use SqlSemantics\Platform\PostgreSql\Statement\OutputNaming;
use SqlSemantics\Platform\PostgreSql\Statement\Query\Clause\ValuesRow;

/**
 * VALUES: a constant table of rows written in the query.
 *
 * Mirrors the `valuesLists` form of PostgreSQL's `SelectStmt`. The facts
 * follow PG-VALUES-001.
 * Source: https://www.postgresql.org/docs/17/sql-values.html.
 *
 * @visibility SqlSemantics
 */
final class ValuesList implements Statement, Query, OutputNaming
{
    use Snapshot;

    /**
     * @var non-empty-list<ValuesRow> The rows in written order
     */
    public readonly array $rows;

    /**
     * @param list<ValuesRow> $rows The rows in written order; at least one
     */
    public function __construct(array $rows)
    {
        $this->rows = Check::listOf($rows, ValuesRow::class, 'A VALUES list holds at least one row.', 1);
    }

    /**
     * Answers the name of the first column.
     */
    public function outputName(): Name
    {
        return new Name('column1');
    }

    /**
     * Derives the list as a statement root.
     */
    public function deriveStatement(Derivation $derivation): void
    {
        (new QueryRoots())->derive($this, $derivation);
    }

    /**
     * Derives the rows and the output columns.
     */
    public function deriveQuery(Derivation $derivation, Environment $outer): QueryFact
    {
        return (new ValuesFacts())->derive($this, $derivation, $outer);
    }

    /**
     * Writes VALUES and the rows.
     */
    public function render(Output $out): void
    {
        $out->keyword('VALUES')->list($this->rows);
    }
}
