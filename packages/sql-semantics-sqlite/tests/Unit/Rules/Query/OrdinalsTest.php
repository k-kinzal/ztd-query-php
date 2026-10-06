<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Query;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Rules\Query\Ordinals;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Collate;
use SqlSemantics\Platform\Sqlite\Statement\Expression\ColumnUse;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Grouped;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Literal\HexLiteral;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Literal\IntegerLiteral;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Literal\RealLiteral;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Literal\TextLiteral;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Operator\Unary;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Operator\UnaryOperator;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(Ordinals::class)]
#[Small]
final class OrdinalsTest extends TestCase
{
    public function testValueReadsAnIntegerLiteralThatFitsThirtyTwoBits(): void
    {
        $ordinals = new Ordinals();

        self::assertSame(1, $ordinals->value(new IntegerLiteral('1')));
        self::assertSame(0, $ordinals->value(new IntegerLiteral('0')));
        self::assertSame(12, $ordinals->value(new IntegerLiteral('0012')));
        self::assertSame(2147483647, $ordinals->value(new IntegerLiteral('2147483647')));
        self::assertNull($ordinals->value(new IntegerLiteral('2147483648')));
        self::assertNull($ordinals->value(new IntegerLiteral('99999999999')));
    }

    public function testValueReadsAHexadecimalLiteralOfAtMostEightSignificantDigits(): void
    {
        $ordinals = new Ordinals();

        self::assertSame(1, $ordinals->value(new HexLiteral('1')));
        self::assertSame(255, $ordinals->value(new HexLiteral('00000000FF')));
        self::assertSame(2147483647, $ordinals->value(new HexLiteral('7FFFFFFF')));
        self::assertNull($ordinals->value(new HexLiteral('80000000')));
        self::assertNull($ordinals->value(new HexLiteral('100000000')));
    }

    public function testValueLooksThroughSignsCollationsAndParentheses(): void
    {
        $ordinals = new Ordinals();

        self::assertSame(-2, $ordinals->value(new Unary(UnaryOperator::Minus, new IntegerLiteral('2'))));
        self::assertSame(2, $ordinals->value(new Unary(UnaryOperator::Minus, new Unary(UnaryOperator::Minus, new IntegerLiteral('2')))));
        self::assertSame(3, $ordinals->value(new Unary(UnaryOperator::Plus, new Grouped(new IntegerLiteral('3')))));
        self::assertSame(4, $ordinals->value(new Collate(new Grouped(new Unary(UnaryOperator::Minus, new Unary(UnaryOperator::Minus, new HexLiteral('4')))), new Name('nocase'))));
        self::assertSame(5, $ordinals->value(new Grouped(new Collate(new IntegerLiteral('5'), new Name('binary')))));
    }

    public function testValueIsNullForAnOrdinaryExpression(): void
    {
        $ordinals = new Ordinals();

        self::assertNull($ordinals->value(new ColumnUse(new Name('a'))));
        self::assertNull($ordinals->value(new TextLiteral('1')));
        self::assertNull($ordinals->value(new RealLiteral('1', '0')));
        self::assertNull($ordinals->value(new Unary(UnaryOperator::Not, new IntegerLiteral('1'))));
        self::assertNull($ordinals->value(new Unary(UnaryOperator::BitNot, new IntegerLiteral('1'))));
    }
}
