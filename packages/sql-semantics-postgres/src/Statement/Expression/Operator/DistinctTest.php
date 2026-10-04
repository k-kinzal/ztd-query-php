<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Expression\Operator;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Rules\Expression\Precedence;
use SqlSemantics\Platform\PostgreSql\Rules\Expression\Typing\OperatorTyping;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Fact\ScalarFact;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Type\Nullability;

/**
 * A comparison that treats NULL as a comparable value: `a IS [NOT] DISTINCT FROM b`.
 *
 * Mirrors PostgreSQL's `A_Expr` node of kind `AEXPR_DISTINCT` or
 * `AEXPR_NOT_DISTINCT`; the server evaluates it with the `=` operator of the
 * operand types.
 *
 * Rule: PG-DISTINCT-001. Facts: the type of `=` over the operands
 * (PG-OPERATOR-TYPING-001), never NULL. Both operands must keep their place
 * without parentheses (PG-PRECEDENCE-001).
 * Source: https://www.postgresql.org/docs/17/functions-comparison.html. Status: Implemented.
 *
 * @visibility public
 * @example A distinctness test is never NULL
 *     (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('SELECT 1 IS DISTINCT FROM NULL')->field(0)->nullability // => \SqlSemantics\Statement\Type\Nullability::NotNull
 */
final class DistinctTest implements Scalar
{
    use Snapshot;

    /**
     * @param Scalar $left The left operand
     * @param Scalar $right The right operand
     * @param bool $negated Whether NOT is written, so that the test is for equality
     */
    public function __construct(public readonly Scalar $left, public readonly Scalar $right, public readonly bool $negated)
    {
        $precedence = new Precedence();
        Check::input($precedence->before($left, Precedence::IS), 'The left operand needs parentheses to keep its place.');
        Check::input($precedence->after($right, Precedence::IS), 'The right operand needs parentheses to keep its place.');
    }

    /**
     * Derives the operands and the type of their equality.
     */
    public function deriveScalar(Derivation $derivation, Environment $environment): ScalarFact
    {
        $left = $derivation->scalar($this->left, $environment);
        $right = $derivation->scalar($this->right, $environment);

        return new ScalarFact((new OperatorTyping())->named($derivation->context, '=', $left->type, $right->type), Nullability::NotNull);
    }

    /**
     * Writes the operands around IS [NOT] DISTINCT FROM.
     */
    public function render(Output $out): void
    {
        $out->node($this->left)->keyword('IS');
        if ($this->negated) {
            $out->keyword('NOT');
        }
        $out->keyword('DISTINCT', 'FROM')->node($this->right);
    }
}
