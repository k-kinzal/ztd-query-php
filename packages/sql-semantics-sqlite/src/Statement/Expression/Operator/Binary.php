<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Statement\Expression\Operator;

use SqlSemantics\Platform\Sqlite\Model\Construction\Derivation;
use SqlSemantics\Platform\Sqlite\Model\Diagnostic\Check;
use SqlSemantics\Platform\Sqlite\Model\Rendering\Output;
use SqlSemantics\Platform\Sqlite\Model\Resolution\Environment;
use SqlSemantics\Platform\Sqlite\Model\Statement\Fact\ScalarFact;
use SqlSemantics\Platform\Sqlite\Model\Statement\Scalar;
use SqlSemantics\Platform\Sqlite\Model\Statement\Snapshot;
use SqlSemantics\Platform\Sqlite\Rules\Expression\Precedence;
use SqlSemantics\Platform\Sqlite\Rules\Expression\RowValueUse;
use SqlSemantics\Platform\Sqlite\Rules\Typing\Operators;

/**
 * A binary operation over two operands, in the order written.
 *
 * Rule: SQLITE-BINARY-001. The facts follow SQLITE-OPERATOR-RESULT-001. A
 * comparison takes rows of one width on both sides and every other operator
 * takes single values (SQLITE-ROW-VALUE-USE-001). Both operands must keep
 * their place without parentheses (SQLITE-PRECEDENCE-001): the left operand
 * may not end in a weaker operator, the right operand may not start with an
 * operator that is not tighter, and the right operand of IS may not be a
 * prefix NOT, which SQLite reads as IS NOT.
 * Source: https://sqlite.org/lang_expr.html#operators_and_parse_affecting_attributes,
 * https://sqlite.org/rowvalue.html. Status: Implemented.
 *
 * @visibility SqlSemantics
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
        $rows = new RowValueUse();
        if ($this->operator->level() === Precedence::EQUALITY || $this->operator->level() === Precedence::COMPARISON) {
            $rows->uniform([$left, $right], $derivation);
        } else {
            $rows->single($left, $derivation);
            $rows->single($right, $derivation);
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
