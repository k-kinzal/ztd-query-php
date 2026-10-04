<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Expression\Operator;

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
 * A range test: `a [NOT] BETWEEN [SYMMETRIC] low AND high`.
 *
 * Mirrors PostgreSQL's `A_Expr` node of kind `AEXPR_BETWEEN`,
 * `AEXPR_NOT_BETWEEN`, `AEXPR_BETWEEN_SYM` or `AEXPR_NOT_BETWEEN_SYM`. The
 * optional ASYMMETRIC is the default and requests nothing (see the noise
 * table). SYMMETRIC accepts the bounds in either order.
 *
 * Rule: PG-BETWEEN-001. Facts: `a >= low AND a <= high`: the conjunction of
 * the two comparisons (PG-OPERATOR-TYPING-001), NULL when an operand can be.
 * The value must keep its place before BETWEEN, the low bound is a `b_expr`,
 * and the high bound must keep its place (PG-PRECEDENCE-001).
 * Source: https://www.postgresql.org/docs/17/functions-comparison.html#FUNCTIONS-COMPARISON-PRED-TABLE. Status: Implemented.
 *
 * @visibility public
 * @example Reading a symmetric range test
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('SELECT 2 NOT BETWEEN SYMMETRIC 3 AND 1');
 *     [$query->field(0)->expression->negated, $query->field(0)->expression->symmetric, $query->toString()] // => [true, true, 'SELECT 2 NOT BETWEEN SYMMETRIC 3 AND 1']
 */
final class Between implements Scalar
{
    use Snapshot;

    /**
     * @param Scalar $operand The tested value
     * @param bool $negated Whether NOT is written
     * @param bool $symmetric Whether SYMMETRIC is written
     * @param Scalar $low The first bound, a `b_expr`
     * @param Scalar $high The second bound
     */
    public function __construct(
        public readonly Scalar $operand,
        public readonly bool $negated,
        public readonly bool $symmetric,
        public readonly Scalar $low,
        public readonly Scalar $high,
    ) {
        $precedence = new Precedence();
        Check::input($precedence->before($operand, Precedence::PATTERN), 'The tested value needs parentheses to keep its place.');
        Check::input($precedence->restricted($low), 'The low bound of BETWEEN needs parentheses: it is a b_expr.');
        Check::input($precedence->after($high, Precedence::PATTERN), 'The high bound needs parentheses to keep its place.');
    }

    /**
     * Derives the value and the bounds, and the comparisons of the value with them.
     */
    public function deriveScalar(Derivation $derivation, Environment $environment): ScalarFact
    {
        $operand = $derivation->scalar($this->operand, $environment);
        $low = $derivation->scalar($this->low, $environment);
        $high = $derivation->scalar($this->high, $environment);
        $typing = new OperatorTyping();

        return new ScalarFact(
            $typing->both($typing->named($derivation->context, '>=', $operand->type, $low->type), $typing->named($derivation->context, '<=', $operand->type, $high->type)),
            (new OperandChecks())->nullability([$operand, $low, $high]),
        );
    }

    /**
     * Writes the value, [NOT] BETWEEN [SYMMETRIC] and the bounds.
     */
    public function render(Output $out): void
    {
        $out->node($this->operand);
        if ($this->negated) {
            $out->keyword('NOT');
        }
        $out->keyword('BETWEEN');
        if ($this->symmetric) {
            $out->keyword('SYMMETRIC');
        }
        $out->node($this->low)->keyword('AND')->node($this->high);
    }
}
