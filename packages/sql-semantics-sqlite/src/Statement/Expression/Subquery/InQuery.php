<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Statement\Expression\Subquery;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\Sqlite\Rules\Expression\Precedence;
use SqlSemantics\Platform\Sqlite\Rules\Expression\RowValues;
use SqlSemantics\Platform\Sqlite\Statement\Type\Storage;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Fact\ScalarFact;
use SqlSemantics\Statement\Query;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\Nullability;

/**
 * A membership test against the rows of a query, optionally negated.
 *
 * Rule: SQLITE-IN-QUERY-001. The query is derived inside the environment of
 * the expression. The result is INTEGER; it can be NULL when the operand or
 * a compared column can be, and it depends on the missing inputs when the
 * shape of the query is open. The query returns as many columns as the
 * operand has values (SQLITE-ROW-VALUE-USE-001). The operand may not end in an operator weaker
 * than the equality group (SQLITE-PRECEDENCE-001).
 * Source: https://sqlite.org/lang_expr.html#the_in_and_not_in_operators.
 * Status: Implemented.
 *
 * @visibility public
 * @example Reading a membership test against a query
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\Sqlite\Dialect::Sqlite))->analyze('SELECT 1 IN (SELECT 2)');
 *     [$query->statement->columns[0]->expression->negated, $query->field(0)->nullability] // => [false, \SqlSemantics\Statement\Type\Nullability::NotNull]
 */
final class InQuery implements Scalar
{
    use Snapshot;

    /**
     * @param Scalar $operand The tested expression
     * @param Query $query The query
     * @param bool $negated Whether NOT is written before IN
     */
    public function __construct(public readonly Scalar $operand, public readonly Query $query, public readonly bool $negated = false)
    {
        Check::input((new Precedence())->closing($operand) >= Precedence::EQUALITY, 'The operand needs parentheses to keep its place.');
    }

    /**
     * Derives the operand, the query and the result.
     */
    public function deriveScalar(Derivation $derivation, Environment $environment): ScalarFact
    {
        $operand = $derivation->scalar($this->operand, $environment);
        $nullability = $operand->nullability;
        $fact = $derivation->query($this->query, $environment);
        if ($fact->shape->complete()) {
            (new RowValues())->membership($operand, count($fact->shape->slots), $derivation);
        } else {
            $nullability = $nullability->propagate(Nullability::Dependent);
        }
        foreach ($fact->shape->slots as $slot) {
            $nullability = $nullability->propagate($slot->nullability);
        }

        return new ScalarFact(new Known(Storage::Integer), $nullability);
    }

    /**
     * Writes the test.
     */
    public function render(Output $out): void
    {
        $out->node($this->operand);
        if ($this->negated) {
            $out->keyword('NOT');
        }
        $out->keyword('IN')->symbol('(')->node($this->query)->symbol(')');
    }
}
