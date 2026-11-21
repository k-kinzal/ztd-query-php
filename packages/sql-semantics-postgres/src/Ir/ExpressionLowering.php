<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir;

use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\InvalidConstruction;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Scalar;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\BinaryOperation;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\ColumnReference as ParsedColumn;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Grouped;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Operator\UnaryOperation;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\BooleanLiteral as ParsedBoolean;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\Constant;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\IntegerConstant;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\NullLiteral as ParsedNull;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\NumericConstant;

/**
 * Lowers resolved int4 projection operands.
 *
 * @visibility SqlSemantics\Platform\PostgreSql
 */
final class ExpressionLowering
{
    /**
     * Converts a resolved operand, including the exact builtin operator identity.
     *
     * @throws InvalidConstruction When the operand has no representation in this domain
     */
    public function lower(
        Scalar $expression,
        SourceBinding $binding,
        bool $catalogFirst,
    ): Arithmetic|ColumnReference|IntegerLiteral|BooleanLiteral|NullLiteral {
        while ($expression instanceof Grouped) {
            $expression = $expression->operand;
        }
        if ($expression instanceof BinaryOperation) {
            return $this->arithmetic($expression, $binding, $catalogFirst);
        }
        if ($expression instanceof Constant && $expression->value instanceof IntegerConstant) {
            return new IntegerLiteral($expression->value->digits);
        }
        if ($expression instanceof UnaryOperation && $expression->operator->name->value === '-' && !$expression->operator->explicit && $expression->operand instanceof Constant) {
            $number = $expression->operand->value;
            if ($number instanceof IntegerConstant && $catalogFirst) {
                return new IntegerLiteral($number->digits === '0' ? '0' : '-' . $number->digits);
            }
            if ($number instanceof NumericConstant && $number->text === '2147483648') {
                return new IntegerLiteral('-2147483648');
            }
        }

        return match (true) {
            $expression instanceof ParsedBoolean => new BooleanLiteral($expression->value),
            $expression instanceof ParsedNull => new NullLiteral(),
            $expression instanceof ParsedColumn => $binding->reference($expression),
            default => throw new InvalidConstruction('The operand has no complete PostgreSQL projection representation.'),
        };
    }

    /**
     * Binds the exact builtin operator and checks each operand's int4 role.
     */
    private function arithmetic(BinaryOperation $expression, SourceBinding $binding, bool $catalogFirst): Arithmetic
    {
        $qualifiers = $expression->operator->qualifiers;
        Check::input(
            ($qualifiers === [] && $catalogFirst) || (count($qualifiers) === 1 && $qualifiers[0]->value === 'pg_catalog'),
            'Integer arithmetic must resolve to a pg_catalog operator.',
        );
        $operator = ArithmeticOperator::tryFrom($expression->operator->name->value);
        Check::input($operator !== null, 'The projection supports addition, subtraction and multiplication.');
        $left = $this->lower($expression->left, $binding, $catalogFirst);
        $right = $this->lower($expression->right, $binding, $catalogFirst);
        Check::input(
            !$left instanceof BooleanLiteral && !$left instanceof NullLiteral && !$right instanceof BooleanLiteral && !$right instanceof NullLiteral,
            'Arithmetic operands must have the int4 role.',
        );

        return new Arithmetic($operator, $left, $right);
    }
}
