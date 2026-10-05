<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Expression;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;
use SqlSemantics\Platform\Sqlite\Lowering\Expression\OperatorRule;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Literal\IntegerLiteral;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Operator\Binary;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Operator\BinaryOperator;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Operator\Unary;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Operator\UnaryOperator;
use SqlSemantics\Platform\Sqlite\Statement\Query\ResultColumn;
use SqlSemantics\Platform\Sqlite\Statement\Query\Select;

#[CoversClass(OperatorRule::class)]
#[Medium]
final class OperatorRuleTest extends TestCase
{
    public function testExpressionLowersEveryBinaryOperatorByItsSpelling(): void
    {
        $operation = (new Semantics(Dialect::Sqlite))->analyze('SELECT a AND b, a OR b, a < b, a > b, a <= b, a >= b, a = b, a == b, a <> b, a != b, a & b, a | b, a << b, a >> b, a + b, a - b, a * b, a / b, a % b, a || b, a -> b, a ->> b FROM t');

        self::assertInstanceOf(Select::class, $operation->statement);
        self::assertSame([
            BinaryOperator::And, BinaryOperator::Or, BinaryOperator::Less, BinaryOperator::Greater, BinaryOperator::LessOrEqual, BinaryOperator::GreaterOrEqual,
            BinaryOperator::Equal, BinaryOperator::DoubleEqual, BinaryOperator::NotEqual, BinaryOperator::BangEqual,
            BinaryOperator::BitAnd, BinaryOperator::BitOr, BinaryOperator::ShiftLeft, BinaryOperator::ShiftRight,
            BinaryOperator::Add, BinaryOperator::Subtract, BinaryOperator::Multiply, BinaryOperator::Divide, BinaryOperator::Modulo,
            BinaryOperator::Concat, BinaryOperator::Extract, BinaryOperator::ExtractValue,
        ], array_map(static function (object $column): BinaryOperator {
            self::assertInstanceOf(ResultColumn::class, $column);
            self::assertInstanceOf(Binary::class, $column->expression);

            return $column->expression->operator;
        }, $operation->statement->columns));
        self::assertSame('SELECT a AND b, a OR b, a < b, a > b, a <= b, a >= b, a = b, a == b, a <> b, a != b, a & b, a | b, a << b, a >> b, a + b, a - b, a * b, a / b, a % b, a || b, a -> b, a ->> b FROM t', $operation->toString());
    }

    public function testExpressionLowersTheIsFamily(): void
    {
        $operation = (new Semantics(Dialect::Sqlite))->analyze('SELECT a IS b, a IS NOT b, a IS DISTINCT FROM b, a IS NOT DISTINCT FROM b FROM t');

        self::assertInstanceOf(Select::class, $operation->statement);
        self::assertSame([BinaryOperator::Is, BinaryOperator::IsNot, BinaryOperator::IsDistinctFrom, BinaryOperator::IsNotDistinctFrom], array_map(static function (object $column): BinaryOperator {
            self::assertInstanceOf(ResultColumn::class, $column);
            self::assertInstanceOf(Binary::class, $column->expression);

            return $column->expression->operator;
        }, $operation->statement->columns));
        self::assertSame('SELECT a IS b, a IS NOT b, a IS DISTINCT FROM b, a IS NOT DISTINCT FROM b FROM t', $operation->toString());
    }

    public function testExpressionLowersThePrefixOperators(): void
    {
        $operation = (new Semantics(Dialect::Sqlite))->analyze('SELECT NOT a AS c1, ~a AS c2, +a AS c3, -a AS c4 FROM t');

        self::assertInstanceOf(Select::class, $operation->statement);
        self::assertSame([UnaryOperator::Not, UnaryOperator::BitNot, UnaryOperator::Plus, UnaryOperator::Minus], array_map(static function (object $column): UnaryOperator {
            self::assertInstanceOf(ResultColumn::class, $column);
            self::assertInstanceOf(Unary::class, $column->expression);

            return $column->expression->operator;
        }, $operation->statement->columns));
        self::assertSame('SELECT NOT a AS c1, ~ a AS c2, + a AS c3, - a AS c4 FROM t', $operation->toString());
    }

    public function testExpressionKeepsTheOperandsInWrittenOrderAndTheStructureTheParserChose(): void
    {
        $operation = (new Semantics(Dialect::Sqlite))->analyze('SELECT 1 + 2 * 3, 1 - 2 - 3');

        self::assertInstanceOf(Select::class, $operation->statement);
        self::assertInstanceOf(ResultColumn::class, $operation->statement->columns[0]);
        self::assertInstanceOf(ResultColumn::class, $operation->statement->columns[1]);
        $sum = $operation->statement->columns[0]->expression;
        self::assertInstanceOf(Binary::class, $sum);
        self::assertSame(BinaryOperator::Add, $sum->operator);
        self::assertInstanceOf(IntegerLiteral::class, $sum->left);
        self::assertInstanceOf(Binary::class, $sum->right);
        self::assertSame(BinaryOperator::Multiply, $sum->right->operator);
        $difference = $operation->statement->columns[1]->expression;
        self::assertInstanceOf(Binary::class, $difference);
        self::assertInstanceOf(Binary::class, $difference->left);
        self::assertInstanceOf(IntegerLiteral::class, $difference->left->left);
        self::assertSame('1', $difference->left->left->digits);
        self::assertInstanceOf(IntegerLiteral::class, $difference->right);
        self::assertSame('3', $difference->right->digits);
        self::assertSame('SELECT 1 + 2 * 3, 1 - 2 - 3', $operation->toString());
    }
}
