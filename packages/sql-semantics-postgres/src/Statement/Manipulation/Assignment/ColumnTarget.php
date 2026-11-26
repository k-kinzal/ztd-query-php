<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Assignment;

use SqlSemantics\Platform\PostgreSql\Model\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Model\Contract\NameUse;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\InvalidConstruction;
use SqlSemantics\Platform\PostgreSql\Model\Rendering\Output;
use SqlSemantics\Platform\PostgreSql\Model\Resolution\Environment;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;
use SqlSemantics\Platform\PostgreSql\Statement\Clause;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\IndirectionStep;

/**
 * A column an INSERT column list or an assignment writes, with the subscripts and fields of the part written.
 *
 * Mirrors the `name` and `indirection` of PostgreSQL's `ResTarget` in an
 * INSERT column list or an UPDATE SET item. The name is always a column of
 * the written table, never a table qualifier.
 * Source: https://www.postgresql.org/docs/17/sql-insert.html, https://www.postgresql.org/docs/17/sql-update.html.
 *
 * @visibility SqlSemantics
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
