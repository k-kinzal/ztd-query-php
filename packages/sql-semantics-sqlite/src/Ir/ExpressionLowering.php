<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Ir;

use SqlSemantics\Platform\Sqlite\Ir\Predicate\Comparison;
use SqlSemantics\Platform\Sqlite\Ir\Predicate\ComparisonOperator;
use SqlSemantics\Platform\Sqlite\Ir\Predicate\Logical;
use SqlSemantics\Platform\Sqlite\Ir\Predicate\LogicalOperator;
use SqlSemantics\Platform\Sqlite\Model\Diagnostic\Check;
use SqlSemantics\Platform\Sqlite\Model\Diagnostic\InvalidConstruction;
use SqlSemantics\Platform\Sqlite\Model\Statement\Scalar;
use SqlSemantics\Platform\Sqlite\Statement\Expression\BindParameter;
use SqlSemantics\Platform\Sqlite\Statement\Expression\ColumnUse;
use SqlSemantics\Platform\Sqlite\Statement\Expression\DoubleQuotedWord;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Grouped;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Literal\BlobLiteral as ParsedBlob;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Literal\HexLiteral as ParsedHex;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Literal\IntegerLiteral as ParsedInteger;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Literal\NullLiteral as ParsedNull;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Literal\RealLiteral as ParsedReal;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Literal\TextLiteral as ParsedText;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Operator\Binary;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Operator\BinaryOperator;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Operator\Unary;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Operator\UnaryOperator;
use SqlSemantics\Platform\Sqlite\Statement\Expression\ParameterPrefix;

/**
 * Resolves the closed scalar expressions used by queries and writes.
 *
 * @visibility SqlSemantics\Platform\Sqlite
 */
final class ExpressionLowering
{
    /**
     * Converts a scalar expression and resolves its column references.
     *
     * @throws InvalidConstruction When the expression is unsupported or a reference is unresolved
     */
    public function lower(Scalar $expression, SourceBinding $binding): Arithmetic|BlobLiteral|ColumnReference|Comparison|HexIntegerLiteral|IntegerLiteral|Logical|NullLiteral|Parameter|RealLiteral|TextLiteral
    {
        $expression = $this->ungroup($expression);

        if ($expression instanceof Binary) {
            $operator = $this->operator($expression->operator);
            $left = $this->lower($expression->left, $binding);
            $right = $this->lower($expression->right, $binding);

            return match (true) {
                $operator instanceof ArithmeticOperator => new Arithmetic($operator, $left, $right),
                $operator instanceof ComparisonOperator => new Comparison($operator, $left, $right),
                $operator instanceof LogicalOperator => new Logical($operator, $left, $right),
            };
        }
        if ($expression instanceof Unary && in_array($expression->operator, [UnaryOperator::Plus, UnaryOperator::Minus], true)) {
            $operand = $this->ungroup($expression->operand);
            Check::input(
                $operand instanceof ParsedInteger || $operand instanceof ParsedHex || $operand instanceof ParsedReal,
                'A complete signed literal requires a single numeric literal operand.'
            );

            return $this->number($operand, $expression->operator === UnaryOperator::Minus);
        }

        return match (true) {
            $expression instanceof ParsedInteger, $expression instanceof ParsedHex, $expression instanceof ParsedReal => $this->number($expression, false),
            $expression instanceof ParsedText => new TextLiteral($expression->value),
            $expression instanceof ParsedBlob => new BlobLiteral($expression->hex),
            $expression instanceof ParsedNull => new NullLiteral(),
            $expression instanceof BindParameter && $expression->prefix === ParameterPrefix::Question => new Parameter($expression->label),
            $expression instanceof ColumnUse, $expression instanceof DoubleQuotedWord => $binding->reference($expression),
            default => throw new InvalidConstruction('This expression has no complete SQLite scalar representation.'),
        };
    }

    private function ungroup(Scalar $scalar): Scalar
    {
        while ($scalar instanceof Grouped) {
            $scalar = $scalar->operand;
        }

        return $scalar;
    }

    private function number(ParsedInteger|ParsedHex|ParsedReal $scalar, bool $negative): IntegerLiteral|HexIntegerLiteral|RealLiteral
    {
        if ($scalar instanceof ParsedHex) {
            $digits = ltrim($scalar->digits, '0');

            return new HexIntegerLiteral($digits === '' ? '0' : $digits, $negative);
        }
        if ($scalar instanceof ParsedReal) {
            return new RealLiteral($scalar->whole, $scalar->fraction, $scalar->exponent, $negative);
        }

        $digits = ltrim($scalar->digits, '0');
        $boundary = $negative ? '9223372036854775808' : '9223372036854775807';
        if (strlen($digits) > 19 || (strlen($digits) === 19 && strcmp($digits, $boundary) > 0)) {
            return new RealLiteral($scalar->digits, null, null, $negative);
        }

        return new IntegerLiteral($digits === '' ? '0' : ($negative ? '-' : '') . $digits);
    }

    private function operator(BinaryOperator $operator): ArithmeticOperator|ComparisonOperator|LogicalOperator
    {
        return match ($operator) {
            BinaryOperator::Add => ArithmeticOperator::Add,
            BinaryOperator::Subtract => ArithmeticOperator::Subtract,
            BinaryOperator::Multiply => ArithmeticOperator::Multiply,
            BinaryOperator::And => LogicalOperator::And,
            BinaryOperator::Or => LogicalOperator::Or,
            BinaryOperator::Equal, BinaryOperator::DoubleEqual => ComparisonOperator::Equal,
            BinaryOperator::NotEqual, BinaryOperator::BangEqual => ComparisonOperator::NotEqual,
            BinaryOperator::Is, BinaryOperator::IsNotDistinctFrom => ComparisonOperator::Is,
            BinaryOperator::IsNot, BinaryOperator::IsDistinctFrom => ComparisonOperator::IsNot,
            BinaryOperator::Less => ComparisonOperator::Less,
            BinaryOperator::LessOrEqual => ComparisonOperator::LessOrEqual,
            BinaryOperator::Greater => ComparisonOperator::Greater,
            BinaryOperator::GreaterOrEqual => ComparisonOperator::GreaterOrEqual,
            BinaryOperator::Divide, BinaryOperator::Modulo,
            BinaryOperator::BitAnd, BinaryOperator::BitOr, BinaryOperator::ShiftLeft, BinaryOperator::ShiftRight,
            BinaryOperator::Concat, BinaryOperator::Extract, BinaryOperator::ExtractValue
                => throw new InvalidConstruction('This operation has no complete SQLite scalar representation.'),
        };
    }
}
