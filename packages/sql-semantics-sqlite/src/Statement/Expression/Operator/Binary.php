<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Statement\Expression\Operator;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\Sqlite\Rules\Expression\Precedence;
use SqlSemantics\Platform\Sqlite\Rules\Typing\Operators;
use SqlSemantics\Platform\Sqlite\Statement\Query\Problem\ArityMismatch;
use SqlSemantics\Platform\Sqlite\Statement\Query\Problem\ArityRule;
use SqlSemantics\Platform\Sqlite\Statement\Type\Vector;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Fact\ScalarFact;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Type\Known;

/**
 * A binary operation over two operands, in the order written.
 *
 * Rule: SQLITE-BINARY-001. The facts follow SQLITE-OPERATOR-RESULT-001. Two
 * row values of different widths cannot be compared. Both operands must keep
 * their place without parentheses (SQLITE-PRECEDENCE-001): the left operand
 * may not end in a weaker operator, the right operand may not start with an
 * operator that is not tighter, and the right operand of IS may not be a
 * prefix NOT, which SQLite reads as IS NOT.
 * Source: https://sqlite.org/lang_expr.html#operators_and_parse_affecting_attributes,
 * https://sqlite.org/rowvalue.html. Status: Implemented.
 *
 * @visibility public
 * @example Reading both operands
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\Sqlite\Dialect::Sqlite))->analyze('SELECT 1 + 2');
 *     [$query->statement->columns[0]->expression->left->digits, $query->statement->columns[0]->expression->right->digits] // => ['1', '2']
 * @example Refusing an operand that the rendered SQL would associate differently
 *     $sum = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\Sqlite\Dialect::Sqlite))->analyze('SELECT 1 + 2')->statement->columns[0]->expression;
 *     new \SqlSemantics\Platform\Sqlite\Statement\Expression\Operator\Binary(\SqlSemantics\Platform\Sqlite\Statement\Expression\Operator\BinaryOperator::Multiply, $sum, new \SqlSemantics\Platform\Sqlite\Statement\Expression\Literal\IntegerLiteral('3')) // throws \SqlSemantics\Diagnostic\InvalidConstruction
 */
final class Binary implements Scalar
{
    use Snapshot;

    /**
     * @param BinaryOperator $operator The operator
     * @param Scalar $left The left operand
     * @param Scalar $right The right operand
     */
    public function __construct(public readonly BinaryOperator $operator, public readonly Scalar $left, public readonly Scalar $right)
    {
        $precedence = new Precedence();
        Check::input($precedence->closing($left) >= $operator->level(), 'The left operand needs parentheses to keep its place.');
        Check::input($precedence->opening($right) > $operator->level(), 'The right operand needs parentheses to keep its place.');
        Check::input($operator !== BinaryOperator::Is || !($right instanceof Unary && $right->operator === UnaryOperator::Not), 'IS followed by a prefix NOT is read as IS NOT.');
    }

    /**
     * Derives both operands and combines their facts.
     */
    public function deriveScalar(Derivation $derivation, Environment $environment): ScalarFact
    {
        $left = $derivation->scalar($this->left, $environment);
        $right = $derivation->scalar($this->right, $environment);
        if ($left->type instanceof Known && $right->type instanceof Known && $left->type->descriptor instanceof Vector && $right->type->descriptor instanceof Vector && $left->type->descriptor->width !== $right->type->descriptor->width) {
            $derivation->report(new ArityMismatch(ArityRule::RowComparison, $left->type->descriptor->width, $right->type->descriptor->width));
        }

        return (new Operators())->binary($this->operator, $left, $right);
    }

    /**
     * Writes the operands around the operator.
     */
    public function render(Output $out): void
    {
        $out->node($this->left);
        if ($this->operator->worded()) {
            $out->keyword(...explode(' ', $this->operator->value));
        } else {
            $out->symbol($this->operator->value);
        }
        $out->node($this->right);
    }
}
