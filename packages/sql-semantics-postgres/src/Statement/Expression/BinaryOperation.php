<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Expression;

use SqlSemantics\Platform\PostgreSql\Model\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Rendering\Output;
use SqlSemantics\Platform\PostgreSql\Model\Resolution\Environment;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Fact\ScalarFact;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Scalar;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;
use SqlSemantics\Platform\PostgreSql\Rules\Expression\Precedence;
use SqlSemantics\Platform\PostgreSql\Rules\Expression\Typing\OperatorTyping;
use SqlSemantics\Platform\PostgreSql\Statement\Name\OperatorName;
use SqlSemantics\Platform\PostgreSql\Statement\OutputNaming;

/**
 * An operator applied to a left and a right operand: `a + b`, `a = b`, `a OPERATOR(s.@@) b`.
 *
 * Mirrors PostgreSQL's `A_Expr` node of kind `AEXPR_OP` with two operands:
 * the operator is a name looked up by the operand types, not a member of a
 * closed set.
 *
 * Rule: PG-BINARY-OPERATION-001. Facts follow PG-OPERATOR-TYPING-001; the
 * catalog operators are strict, so the result can be NULL exactly when an
 * operand can. In an expression a qualified operator is written with the
 * `OPERATOR(...)` syntax. Both operands must keep their place without
 * parentheses (PG-PRECEDENCE-001).
 * Source: https://www.postgresql.org/docs/17/sql-expressions.html#SQL-EXPRESSIONS-OPERATOR-CALLS,
 * https://www.postgresql.org/docs/17/sql-syntax-lexical.html#SQL-PRECEDENCE, https://www.postgresql.org/docs/17/typeconv-oper.html. Status: Implemented.
 *
 * @visibility SqlSemantics
 * @example Rejecting an operand that would need parentheses
 *     $one = new \SqlSemantics\Platform\PostgreSql\Statement\Literal\Constant(new \SqlSemantics\Platform\PostgreSql\Statement\Literal\IntegerConstant('1'));
 *     $two = new \SqlSemantics\Platform\PostgreSql\Statement\Literal\Constant(new \SqlSemantics\Platform\PostgreSql\Statement\Literal\IntegerConstant('2'));
 *     $plus = new \SqlSemantics\Platform\PostgreSql\Statement\Name\OperatorName(new \SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name('+'));
 *     $times = new \SqlSemantics\Platform\PostgreSql\Statement\Name\OperatorName(new \SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name('*'));
 *     new \SqlSemantics\Platform\PostgreSql\Statement\Expression\BinaryOperation($times, new \SqlSemantics\Platform\PostgreSql\Statement\Expression\BinaryOperation($plus, $one, $two), new \SqlSemantics\Platform\PostgreSql\Statement\Literal\NullLiteral()) // throws \SqlSemantics\Platform\PostgreSql\Model\Diagnostic\InvalidConstruction
 */
final class BinaryOperation implements Scalar, OutputNaming
{
    use Snapshot;

    /**
     * @param OperatorName $operator The operator
     * @param Scalar $left The left operand
     * @param Scalar $right The right operand
     */
    public function __construct(public readonly OperatorName $operator, public readonly Scalar $left, public readonly Scalar $right)
    {
        Check::input($operator->explicit || $operator->qualifiers === [], 'An expression writes a qualified operator with OPERATOR(...).');
        $precedence = new Precedence();
        $level = $precedence->operator($operator);
        Check::input($precedence->before($left, $level), 'The left operand needs parentheses to keep its place.');
        Check::input($precedence->after($right, $level), 'The right operand needs parentheses to keep its place.');
    }

    /**
     * Gives a result column no name.
     */
    public function outputName(): ?Name
    {
        return null;
    }

    /**
     * Derives the operands and the result of the operator over their types.
     */
    public function deriveScalar(Derivation $derivation, Environment $environment): ScalarFact
    {
        $left = $derivation->scalar($this->left, $environment);
        $right = $derivation->scalar($this->right, $environment);

        return (new OperatorTyping())->binary($derivation->context, $this->operator, $left, $right);
    }

    /**
     * Writes the left operand, the operator and the right operand.
     */
    public function render(Output $out): void
    {
        $out->node($this->left)->node($this->operator)->node($this->right);
    }
}
