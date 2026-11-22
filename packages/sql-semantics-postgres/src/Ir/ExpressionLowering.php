<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir;

use SqlSemantics\Platform\PostgreSql\Ir\Expression\IntegerCase;
use SqlSemantics\Platform\PostgreSql\Ir\Expression\IntegerCaseLowering;
use SqlSemantics\Platform\PostgreSql\Ir\From\JoinBinding;
use SqlSemantics\Platform\PostgreSql\Ir\Predicate\IntegerDistinctness;
use SqlSemantics\Platform\PostgreSql\Ir\Predicate\IntegerDistinctnessLowering;
use SqlSemantics\Platform\PostgreSql\Ir\Predicate\IntegerRange;
use SqlSemantics\Platform\PostgreSql\Ir\Predicate\IntegerRangeLowering;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\InvalidConstruction;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Scalar;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\BinaryOperation;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\ColumnReference as ParsedColumn;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Constructor\CaseExpression as ParsedCase;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Grouped;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Operator\Between as ParsedRange;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Operator\DistinctTest as ParsedDistinctness;
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
        SourceBinding|JoinBinding $binding,
        bool $catalogFirst,
    ): Arithmetic|ColumnReference|IntegerCase|IntegerLiteral|IntegerDistinctness|IntegerRange|BooleanLiteral|NullLiteral {
        while ($expression instanceof Grouped) {
            $expression = $expression->operand;
        }
        if ($expression instanceof BinaryOperation) {
            return $this->binary($expression, $binding, $catalogFirst);
        }
        if ($expression instanceof Constant && $expression->value instanceof IntegerConstant) {
            return new IntegerLiteral($expression->value->digits);
        }
        if ($expression instanceof UnaryOperation && $expression->operand instanceof Constant
            && [$expression->operator->name->value, $expression->operator->explicit] === ['-', false]) {
            $number = $expression->operand->value;
            if ($number instanceof IntegerConstant && $catalogFirst) {
                return new IntegerLiteral($number->digits === '0' ? '0' : '-' . $number->digits);
            }
            if ($number instanceof NumericConstant && $number->text === '2147483648') {
                return new IntegerLiteral('-2147483648');
            }
        }

        return match (true) {
            $expression instanceof ParsedCase => (new IntegerCaseLowering())->lower($expression, $binding, $catalogFirst),
            $expression instanceof ParsedDistinctness => (new IntegerDistinctnessLowering())->lower($expression, $binding, $catalogFirst),
            $expression instanceof ParsedRange => (new IntegerRangeLowering())->lower($expression, $binding, $catalogFirst),
            $expression instanceof ParsedBoolean => new BooleanLiteral($expression->value),
            $expression instanceof ParsedNull => new NullLiteral(),
            $expression instanceof ParsedColumn => $binding->reference($expression),
            default => throw new InvalidConstruction('The operand has no complete PostgreSQL projection representation.'),
        };
    }

    /**
     * Resolves the exact builtin operation and both of its int4 operands.
     * @throws InvalidConstruction When an operator or operand is outside this checked scope
     */
    public function binary(BinaryOperation $expression, SourceBinding|JoinBinding $binding, bool $catalogFirst): Arithmetic
    {
        $qualifiers = array_column($expression->operator->qualifiers, 'value');
        Check::input(
            ($qualifiers === [] && $catalogFirst) || $qualifiers === ['pg_catalog'],
            'Integer arithmetic must resolve to a pg_catalog operator.',
        );
        $operator = ArithmeticOperator::tryFrom($expression->operator->name->value);
        Check::input($operator !== null, 'The operator has no checked PostgreSQL int4 arithmetic representation.');
        $left = $this->lower($expression->left, $binding, $catalogFirst);
        $right = $this->lower($expression->right, $binding, $catalogFirst);
        $typeError = 'Arithmetic operands must have the int4 role.';
        Check::input(!$left instanceof BooleanLiteral && !$left instanceof NullLiteral && !$left instanceof IntegerRange && !$left instanceof IntegerDistinctness, $typeError);
        Check::input(!$right instanceof BooleanLiteral && !$right instanceof NullLiteral && !$right instanceof IntegerRange && !$right instanceof IntegerDistinctness, $typeError);

        return new Arithmetic($operator, $left, $right);
    }
}
