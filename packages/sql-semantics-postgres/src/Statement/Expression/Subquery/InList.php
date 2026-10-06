<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Expression\Subquery;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Rules\Expression\OperandChecks;
use SqlSemantics\Platform\PostgreSql\Rules\Expression\Precedence;
use SqlSemantics\Platform\PostgreSql\Rules\Expression\Typing\OperatorTyping;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Fact\ScalarFact;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Snapshot;

/**
 * A membership test against a list of values: `a [NOT] IN (v1, v2, …)`.
 *
 * Mirrors PostgreSQL's `A_Expr` node of kind `AEXPR_IN`.
 *
 * Rule: PG-IN-LIST-001. Facts: the conjunction of `a = v` over the values
 * (PG-OPERATOR-TYPING-001), NULL when an operand can be. One value in
 * parentheses that is a scalar subquery is read as `IN (SELECT …)` instead,
 * so a list of one such value cannot be written. The value must keep its
 * place before IN (PG-PRECEDENCE-001).
 * Source: https://www.postgresql.org/docs/17/functions-comparisons.html#FUNCTIONS-COMPARISONS-IN-SCALAR. Status: Implemented.
 *
 * @visibility public
 * @example Reading the values of a list
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('SELECT 1 IN (1, 2, NULL)');
 *     [count($query->field(0)->expression->values), $query->field(0)->nullability] // => [3, \SqlSemantics\Statement\Type\Nullability::Nullable]
 */
final class InList implements Scalar
{
    use Snapshot;

    /**
     * @var non-empty-list<Scalar> The values in order
     */
    public readonly array $values;

    /**
     * @param Scalar $operand The tested value
     * @param bool $negated Whether NOT is written
     * @param list<Scalar> $values The values in order; at least one
     */
    public function __construct(public readonly Scalar $operand, public readonly bool $negated, array $values)
    {
        $this->values = Check::listOf($values, Scalar::class, 'An IN list holds at least one value.', 1);
        Check::input((new Precedence())->before($operand, Precedence::PATTERN), 'The tested value needs parentheses to keep its place.');
        Check::input(count($this->values) > 1 || !$this->values[0] instanceof ScalarSubquery, 'IN with one scalar subquery is read as IN (SELECT ...).');
    }

    /**
     * Derives the value and the list, and the comparisons of the value with each item.
     */
    public function deriveScalar(Derivation $derivation, Environment $environment): ScalarFact
    {
        $operand = $derivation->scalar($this->operand, $environment);
        $facts = [$operand];
        $typing = new OperatorTyping();
        $types = [];
        foreach ($this->values as $value) {
            $fact = $derivation->scalar($value, $environment);
            $facts[] = $fact;
            $types[] = $typing->named($derivation->context, '=', $operand->type, $fact->type);
        }
        $type = array_shift($types);
        foreach ($types as $comparison) {
            $type = $typing->both($type, $comparison);
        }

        return new ScalarFact($type, (new OperandChecks())->nullability($facts));
    }

    /**
     * Writes the value, [NOT] IN and the list in parentheses.
     */
    public function render(Output $out): void
    {
        $out->node($this->operand);
        if ($this->negated) {
            $out->keyword('NOT');
        }
        $out->keyword('IN')->symbol('(')->list($this->values)->symbol(')');
    }
}
