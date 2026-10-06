<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Expression;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Rules\Expression\ColumnNaming;
use SqlSemantics\Platform\PostgreSql\Rules\Expression\Typing\Subscripting;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Step\Slice;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Step\Subscript;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Subquery\ScalarSubquery;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\NamedParameter;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\PositionalParameter;
use SqlSemantics\Platform\PostgreSql\Statement\OutputNaming;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Fact\ScalarFact;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Snapshot;

/**
 * Field selections, subscripts and slices applied to a value: `(x).f`, `$1[2]`, `a[1:2]`, `(SELECT …).f`.
 *
 * Mirrors PostgreSQL's `A_Indirection` node. The grammar applies steps to a
 * parenthesized expression, a parameter, a scalar subquery, and a column
 * reference once a subscript follows its dotted name; a dotted name that
 * ends in `.*` is a `ColumnStar`, which takes further steps only improperly.
 *
 * Rule: PG-INDIRECTION-001. Facts follow PG-SUBSCRIPT-001. An unaliased
 * result column is named after the last field the steps select, and
 * otherwise as the base names it.
 * Source: https://www.postgresql.org/docs/17/sql-expressions.html#SQL-EXPRESSIONS-SUBSCRIPTS,
 * https://www.postgresql.org/docs/17/sql-expressions.html#FIELD-SELECTION. Status: Implemented.
 *
 * @visibility public
 * @example Selecting a field of a row
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('SELECT (ROW(1, 2)).f2');
 *     [$query->field(0)->name->value, $query->field(0)->type->descriptor->name()] // => ['f2', 'integer']
 * @example Rejecting a field step that the dotted name would absorb
 *     $a = new \SqlSemantics\Platform\PostgreSql\Statement\Expression\ColumnReference([new \SqlSemantics\Statement\Identifier\Name('a')]);
 *     new \SqlSemantics\Platform\PostgreSql\Statement\Expression\Indirection($a, [new \SqlSemantics\Platform\PostgreSql\Statement\Expression\FieldSelection(new \SqlSemantics\Statement\Identifier\Name('b'))]) // throws \SqlSemantics\Diagnostic\InvalidConstruction
 */
final class Indirection implements Scalar, OutputNaming
{
    use Snapshot;

    /**
     * @var non-empty-list<IndirectionStep> The steps in order
     */
    public readonly array $steps;

    /**
     * @param Scalar $base The value the steps apply to
     * @param list<IndirectionStep> $steps The steps in order; at least one
     */
    public function __construct(public readonly Scalar $base, array $steps)
    {
        $this->steps = Check::listOf($steps, IndirectionStep::class, 'An indirection has at least one step.', 1);
        Check::input(
            $base instanceof Grouped || $base instanceof PositionalParameter || $base instanceof NamedParameter || $base instanceof ScalarSubquery
                || $base instanceof ColumnStar || $base instanceof ColumnReference,
            'Steps apply to a parenthesized expression, a parameter, a scalar subquery or a column reference.',
        );
        Check::input(!$base instanceof ColumnReference || $this->steps[0] instanceof Subscript || $this->steps[0] instanceof Slice, 'A column reference takes steps once a subscript follows its dotted name.');
    }

    /**
     * Names an unaliased result column after the last selected field, or as the base does.
     */
    public function outputName(): ?Name
    {
        return (new ColumnNaming())->name($this);
    }

    /**
     * Derives the base and the steps, and the value the steps select.
     */
    public function deriveScalar(Derivation $derivation, Environment $environment): ScalarFact
    {
        $base = $derivation->scalar($this->base, $environment);
        foreach ($this->steps as $step) {
            $step->deriveClause($derivation, $environment);
        }

        return (new Subscripting())->apply($derivation, $base, $this->steps, $this->base instanceof ColumnStar);
    }

    /**
     * Writes the base and the steps.
     */
    public function render(Output $out): void
    {
        $out->node($this->base);
        foreach ($this->steps as $step) {
            $out->node($step);
        }
    }
}
