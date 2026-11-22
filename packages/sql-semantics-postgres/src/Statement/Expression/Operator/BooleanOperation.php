<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Expression\Operator;

use SqlSemantics\Platform\PostgreSql\Model\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Rendering\Output;
use SqlSemantics\Platform\PostgreSql\Model\Resolution\Environment;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Fact\ScalarFact;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Scalar;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Type\Known;
use SqlSemantics\Platform\PostgreSql\Rules\Expression\OperandChecks;
use SqlSemantics\Platform\PostgreSql\Rules\Expression\Precedence;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin;

/**
 * A conjunction or disjunction of two conditions: `a AND b`, `a OR b`.
 *
 * Mirrors PostgreSQL's `BoolExpr` node of kind `AND_EXPR` or `OR_EXPR`. The
 * parser flattens a chain into one node; the model keeps the binary
 * structure and the groupings as written, which evaluate the same.
 *
 * Rule: PG-BOOLEAN-OPERATION-001. Facts: `boolean`; an operand whose type is
 * not boolean (after an unknown-typed constant takes boolean) is reported;
 * the result can be NULL when an operand can. Both operands must keep their
 * place without parentheses (PG-PRECEDENCE-001).
 * Source: https://www.postgresql.org/docs/17/functions-logical.html. Status: Implemented.
 *
 * @visibility SqlSemantics
 */
final class BooleanOperation implements Scalar
{
    use Snapshot;

    /**
     * @param BooleanOperator $operator The operator
     * @param Scalar $left The left condition
     * @param Scalar $right The right condition
     */
    public function __construct(public readonly BooleanOperator $operator, public readonly Scalar $left, public readonly Scalar $right)
    {
        $precedence = new Precedence();
        $level = $operator === BooleanOperator::And ? Precedence::AND : Precedence::OR;
        Check::input($precedence->before($left, $level), 'The left operand needs parentheses to keep its place.');
        Check::input($precedence->after($right, $level), 'The right operand needs parentheses to keep its place.');
    }

    /**
     * Derives both conditions and checks that they are boolean.
     */
    public function deriveScalar(Derivation $derivation, Environment $environment): ScalarFact
    {
        $checks = new OperandChecks();
        $word = $this->operator === BooleanOperator::And ? 'AND' : 'OR';
        $left = $derivation->scalar($this->left, $environment);
        $right = $derivation->scalar($this->right, $environment);
        $type = $checks->boolean($derivation, $left->type, $word) ?? $checks->boolean($derivation, $right->type, $word) ?? new Known(Builtin::Bool);

        return new ScalarFact($type, $checks->nullability([$left, $right]));
    }

    /**
     * Writes the conditions around the operator.
     */
    public function render(Output $out): void
    {
        $out->node($this->left)->keyword($this->operator === BooleanOperator::And ? 'AND' : 'OR')->node($this->right);
    }
}
