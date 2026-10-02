<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Expression;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Rules\Expression\ComparisonTyping;
use SqlSemantics\Platform\PostgreSql\Statement\Name\OperatorName;
use SqlSemantics\Platform\PostgreSql\Statement\OutputNaming;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Fact\ScalarFact;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Type\Nullability;
use SqlSemantics\Statement\Type\NullOnly;

/**
 * An operator applied to a left and a right operand.
 *
 * Mirrors PostgreSQL's `A_Expr` node of kind `AEXPR_OP`: the operator is a
 * name resolved against the operand types, not a member of a closed set.
 *
 * Rule: PG-BINARY-OPERATION-001 (slice — the expression family completes or
 * replaces this). The slice accepts the comparison operators over operands
 * that are not themselves operator applications, because comparison does not
 * associate; the family adds the precedence table that decides which operands
 * need parentheses. Facts: the type of PG-OPERATOR-COMPARISON-001; the
 * comparison operators are strict, so the result is NULL exactly when an
 * operand is. Source: https://www.postgresql.org/docs/17/sql-syntax-lexical.html#SQL-PRECEDENCE,
 * https://www.postgresql.org/docs/17/functions-comparison.html. Status: Specified.
 *
 * @visibility public
 * @example Reading a comparison
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('SELECT a FROM t WHERE a >= 1');
 *     [$query->statement->where->operator->name->value, $query->statement->where->right->value->digits] // => ['>=', '1']
 * @example Rejecting an operand that would need parentheses
 *     $one = new \SqlSemantics\Platform\PostgreSql\Statement\Literal\Constant(new \SqlSemantics\Platform\PostgreSql\Statement\Literal\IntegerConstant('1'));
 *     $two = new \SqlSemantics\Platform\PostgreSql\Statement\Literal\Constant(new \SqlSemantics\Platform\PostgreSql\Statement\Literal\IntegerConstant('2'));
 *     $less = new \SqlSemantics\Platform\PostgreSql\Statement\Name\OperatorName(new \SqlSemantics\Statement\Identifier\Name('<'));
 *     $equal = new \SqlSemantics\Platform\PostgreSql\Statement\Name\OperatorName(new \SqlSemantics\Statement\Identifier\Name('='));
 *     new \SqlSemantics\Platform\PostgreSql\Statement\Expression\BinaryOperation($equal, new \SqlSemantics\Platform\PostgreSql\Statement\Expression\BinaryOperation($less, $one, $two), new \SqlSemantics\Platform\PostgreSql\Statement\Literal\NullLiteral()) // throws \SqlSemantics\Diagnostic\InvalidConstruction
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
        Check::input(!$operator->explicit && in_array($operator->name->value, ComparisonTyping::OPERATORS, true), 'This slice builds the comparison operators =, <>, <, >, <= and >= only.');
        Check::input(!$left instanceof self && !$right instanceof self, 'A comparison operand that is itself an operator application needs parentheses.');
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
        $nullability = $left->type instanceof NullOnly || $right->type instanceof NullOnly ? Nullability::Nullable : $left->nullability->propagate($right->nullability);

        return new ScalarFact((new ComparisonTyping())->result($derivation->context, $this->operator, $left->type, $right->type), $nullability);
    }

    /**
     * Writes the left operand, the operator and the right operand.
     */
    public function render(Output $out): void
    {
        $out->node($this->left)->node($this->operator)->node($this->right);
    }
}
