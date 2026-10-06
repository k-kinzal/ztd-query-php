<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Assignment;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\NameUse;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Diagnostic\InvalidConstruction;
use SqlSemantics\Platform\PostgreSql\Statement\Clause;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\IndirectionStep;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Snapshot;

/**
 * A column an INSERT column list or an assignment writes, with the subscripts and fields of the part written.
 *
 * Mirrors the `name` and `indirection` of PostgreSQL's `ResTarget` in an
 * INSERT column list or an UPDATE SET item. The name is always a column of
 * the written table, never a table qualifier.
 * Source: https://www.postgresql.org/docs/17/sql-insert.html, https://www.postgresql.org/docs/17/sql-update.html.
 *
 * @visibility public
 * @example Reading the element an assignment writes
 *     $update = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('UPDATE t SET a[1] = 2');
 *     [$update->statement->assignments[0]->column->column->value, count($update->statement->assignments[0]->column->steps)] // => ['a', 1]
 */
final class ColumnTarget implements Clause
{
    use Snapshot;

    /**
     * @var list<IndirectionStep> The subscripts and field selections after the column name
     */
    public readonly array $steps;

    /**
     * @param Name $column The column name
     * @param list<IndirectionStep> $steps The subscripts and field selections after the column name
     *
     * @throws InvalidConstruction When a step is not an indirection step
     */
    public function __construct(public readonly Name $column, array $steps = [])
    {
        $this->steps = Check::listOf($steps, IndirectionStep::class, 'The steps of an assignment target are indirection steps.');
    }

    /**
     * Derives the subscript expressions of the steps.
     */
    public function deriveClause(Derivation $derivation, Environment $environment): void
    {
        foreach ($this->steps as $step) {
            $step->deriveClause($derivation, $environment);
        }
    }

    /**
     * Writes the column name and the steps.
     */
    public function render(Output $out): void
    {
        $out->name($this->column, NameUse::Column);
        foreach ($this->steps as $step) {
            $out->node($step);
        }
    }
}
