<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Ir;

use SqlSemantics\Platform\MySql\Ir\From\JoinBinding;
use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Model\Diagnostic\InvalidConstruction;
use SqlSemantics\Platform\MySql\Model\Statement\Scalar;
use SqlSemantics\Platform\MySql\Statement\Expression\Grouped;
use SqlSemantics\Platform\MySql\Statement\Expression\Operator\Arithmetic as ParsedArithmetic;
use SqlSemantics\Platform\MySql\Statement\Expression\Operator\Unary;
use SqlSemantics\Platform\MySql\Statement\Expression\Operator\UnaryOperator;
use SqlSemantics\Platform\MySql\Statement\Literal\BooleanLiteral as ParsedBoolean;
use SqlSemantics\Platform\MySql\Statement\Literal\NullLiteral as ParsedNull;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberForm;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Literal\SignedLiteral;
use SqlSemantics\Platform\MySql\Statement\Name\ColumnUse;

/**
 * Lowers resolved signed integer projection operands.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class ExpressionLowering
{
    /**
     * Converts a resolved operand, refusing expressions outside the projection contract.
     *
     * @throws InvalidConstruction When the operand has no representation in this domain
     */
    public function lower(Scalar $expression, SourceBinding|JoinBinding $binding): Arithmetic|ColumnReference|IntegerLiteral|BooleanLiteral|NullLiteral
    {
        while ($expression instanceof Grouped) {
            $expression = $expression->operand;
        }
        if ($expression instanceof ParsedArithmetic) {
            $operator = ArithmeticOperator::tryFrom($expression->operator->value);
            Check::input($operator !== null, 'The operator has no checked MySQL signed arithmetic representation.');
            $left = $this->lower($expression->left, $binding);
            $right = $this->lower($expression->right, $binding);
            Check::input(
                !$left instanceof BooleanLiteral && !$left instanceof NullLiteral && !$right instanceof BooleanLiteral && !$right instanceof NullLiteral,
                'Arithmetic operands must have the signed integer role.',
            );

            return new Arithmetic($operator, $left, $right);
        }
        $negative = false;
        if ($expression instanceof Unary && $expression->operand instanceof NumberLiteral
            && ($expression->operator === UnaryOperator::Minus || $expression->operator === UnaryOperator::Plus)) {
            $negative = $expression->operator === UnaryOperator::Minus;
            $expression = $expression->operand;
        } elseif ($expression instanceof SignedLiteral) {
            $negative = $expression->negative;
            $expression = $expression->number;
        }
        if ($expression instanceof NumberLiteral) {
            $number = $expression;
            Check::input($number->form === NumberForm::Integer, 'The numeric literal must have the signed integer role.');
            $digits = ltrim($number->text, '0');
            $zeroes = min(strlen($number->text) - max(1, strlen($digits)), IntegerLiteral::MAX_LEADING_ZEROES);

            return new IntegerLiteral($digits === '' ? '0' : (($negative ? '-' : '') . $digits), $zeroes, $negative && $digits === '');
        }

        return match (true) {
            $expression instanceof ParsedBoolean => new BooleanLiteral($expression->value),
            $expression instanceof ParsedNull => new NullLiteral(),
            $expression instanceof ColumnUse => $binding->reference($expression),
            default => throw new InvalidConstruction('The operand has no complete MySQL projection representation.'),
        };
    }
}
