<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Expression\Subquery;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Rules\Expression\Precedence;
use SqlSemantics\Platform\PostgreSql\Rules\Expression\Typing\Sublinks;
use SqlSemantics\Platform\PostgreSql\Statement\Name\OperatorName;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Fact\ScalarFact;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Type\Nullability;

/**
 * A comparison with every element of an array: `a op ANY (array)`, `SOME`, `ALL`.
 *
 * Mirrors PostgreSQL's `A_Expr` node of kind `AEXPR_OP_ANY` or `AEXPR_OP_ALL`.
 *
 * Rule: PG-QUANTIFIED-ARRAY-001. Facts: the type of the operator over the
 * value and the array's element type (PG-OPERATOR-TYPING-001); a right
 * operand that is not an array is reported; the result can be NULL. A scalar
 * subquery in the parentheses is read as `op ANY (SELECT …)` instead, so it
 * cannot be the array here. The value must keep its place before the
 * operator (PG-PRECEDENCE-001).
 * Source: https://www.postgresql.org/docs/17/functions-comparisons.html#FUNCTIONS-COMPARISONS-ANY-SOME. Status: Implemented.
 *
 * @visibility public
 * @example Comparing with the elements of an array
 *     (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('SELECT 1 = ANY (ARRAY[1, 2])')->field(0)->type->descriptor->name() // => 'boolean'
 */
final class QuantifiedArray implements Scalar
{
    use Snapshot;

    /**
     * @param Scalar $operand The compared value
     * @param OperatorName|MatchKeyword $comparator The operator
     * @param Quantifier $quantifier The quantifier
     * @param Scalar $array The array; not a scalar subquery
     */
    public function __construct(public readonly Scalar $operand, public readonly OperatorName|MatchKeyword $comparator, public readonly Quantifier $quantifier, public readonly Scalar $array)
    {
        Check::input(!$comparator instanceof OperatorName || $comparator->explicit || $comparator->qualifiers === [], 'An expression writes a qualified operator with OPERATOR(...).');
        Check::input(!$array instanceof ScalarSubquery, 'A scalar subquery after ANY or ALL is read as a subquery comparison.');
        $precedence = new Precedence();
        Check::input($precedence->before($operand, $comparator instanceof OperatorName ? $precedence->operator($comparator) : Precedence::PATTERN), 'The compared value needs parentheses to keep its place.');
    }

    /**
     * Derives the value and the array, and the comparison of the value with its elements.
     */
    public function deriveScalar(Derivation $derivation, Environment $environment): ScalarFact
    {
        $operand = $derivation->scalar($this->operand, $environment);
        $array = $derivation->scalar($this->array, $environment);
        $operator = $this->comparator instanceof OperatorName ? $this->comparator : new OperatorName(new Name($this->comparator->operator()));

        return new ScalarFact((new Sublinks())->elements($derivation, $operand->type, $operator, $array->type), Nullability::Nullable);
    }

    /**
     * Writes the value, the operator, the quantifier and the array in parentheses.
     */
    public function render(Output $out): void
    {
        $out->node($this->operand);
        if ($this->comparator instanceof OperatorName) {
            $out->node($this->comparator);
        } else {
            $out->keyword(...explode(' ', $this->comparator->value));
        }
        $out->keyword($this->quantifier->value)->symbol('(')->node($this->array)->symbol(')');
    }
}
