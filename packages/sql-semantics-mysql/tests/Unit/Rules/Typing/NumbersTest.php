<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Typing;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Rules\Typing\Numbers;
use SqlSemantics\Platform\MySql\Statement\Expression\Grouped;
use SqlSemantics\Platform\MySql\Statement\Expression\Operator\ArithmeticOperator;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Literal\Radix;
use SqlSemantics\Platform\MySql\Statement\Literal\RadixLiteral;
use SqlSemantics\Platform\MySql\Statement\Literal\StringLiteral;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Domain;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(Numbers::class)]
#[Small]
final class NumbersTest extends TestCase
{
    public function testOperandReadsTemporalValuesByTheirFraction(): void
    {
        $numbers = new Numbers();

        self::assertSame(Kind::Integer, $numbers->operand(new Domain(Kind::Date, Field::Date, 10)));
        self::assertSame(Kind::Decimal, $numbers->operand(new Domain(Kind::Time, Field::Time, 12, 3)));
        self::assertSame(Kind::Double, $numbers->operand(Domain::string(3, Collation::binary())));
    }

    public function testDigitsAreThePrecisionAndScaleAnOperandBrings(): void
    {
        $numbers = new Numbers();

        self::assertSame([5, 2], $numbers->digits(Domain::decimal(5, 2)));
        self::assertSame([20, 6], $numbers->digits(new Domain(Kind::DateTime, Field::DateTime, 26, 6)));
        self::assertSame([3, 0], $numbers->digits(Domain::integer(Field::LongLong, 4)));
    }

    public function testBinaryWidensIntegersAndDecimals(): void
    {
        $numbers = new Numbers(4);

        self::assertEquals(Domain::integer(Field::LongLong, 5), $numbers->binary(ArithmeticOperator::Plus, Domain::integer(Field::LongLong, 4), Domain::integer(Field::LongLong, 2)));
        self::assertEquals(Domain::integer(Field::LongLong, 5), $numbers->binary(ArithmeticOperator::Multiply, Domain::integer(Field::LongLong, 4), Domain::integer(Field::LongLong, 2)));
        self::assertEquals(Domain::decimal(5, 4), $numbers->binary(ArithmeticOperator::Divide, Domain::integer(Field::LongLong, 2), Domain::integer(Field::LongLong, 2)));
        self::assertEquals(Domain::double(23), $numbers->binary(ArithmeticOperator::Plus, Domain::integer(), Domain::string(1, Collation::binary())));
        self::assertEquals(Domain::double(2, 0), $numbers->binary(ArithmeticOperator::Plus, Domain::integer(), Domain::null()));
        self::assertEquals(Domain::integer(Field::LongLong, 2), $numbers->binary(ArithmeticOperator::IntegerDivide, Domain::integer(Field::LongLong, 2), Domain::integer(Field::LongLong, 2)));
    }

    public function testDecimalFollowsTheOperator(): void
    {
        $numbers = new Numbers(4);

        self::assertEquals(Domain::decimal(7, 3), $numbers->decimal(ArithmeticOperator::Multiply, [5, 2], [2, 1]));
        self::assertEquals(Domain::decimal(6, 2), $numbers->decimal(ArithmeticOperator::Plus, [5, 2], [2, 1]));
        self::assertEquals(Domain::decimal(5, 2), $numbers->decimal(ArithmeticOperator::Modulo, [5, 2], [2, 1]));
    }

    public function testNegatedWidensUnsignedIntegers(): void
    {
        $numbers = new Numbers();

        self::assertEquals(Domain::integer(Field::LongLong, 21), $numbers->negated(Domain::integer(Field::LongLong, 20, true)));
        self::assertEquals(Domain::decimal(5, 2), $numbers->negated(Domain::decimal(5, 2)));
        self::assertEquals(Domain::double(23), $numbers->negated(Domain::string(1, Collation::binary())));
    }

    public function testNegatedMakesANegativeIntegerConstantADecimal(): void
    {
        $numbers = new Numbers();

        self::assertEquals(Domain::decimal(1, 0), $numbers->negated(Domain::integer(Field::LongLong, 2), true));
        self::assertEquals(Domain::decimal(21, 0), $numbers->negated(Domain::integer(Field::LongLong, 21, true), true));
        self::assertEquals(Domain::decimal(5, 2), $numbers->negated(Domain::decimal(5, 2), true));
    }

    public function testBinaryCountsTheDigitsOfAProductAndAnUnsignedRemainder(): void
    {
        $numbers = new Numbers();

        self::assertSame(42, $numbers->binary(ArithmeticOperator::Multiply, Domain::integer(Field::LongLong, 21, true), Domain::integer(Field::LongLong, 21, true))->length);
        self::assertSame(41, $numbers->binary(ArithmeticOperator::Multiply, Domain::integer(), Domain::integer())->length);
        self::assertSame(66, $numbers->binary(ArithmeticOperator::Multiply, Domain::integer(Field::LongLong, 61), Domain::integer())->length);
        self::assertSame(22, $numbers->binary(ArithmeticOperator::Modulo, Domain::integer(Field::LongLong, 21, true), Domain::integer(Field::LongLong, 2))->length);
        self::assertSame(22, $numbers->binary(ArithmeticOperator::Plus, Domain::integer(), Domain::integer(Field::LongLong, 2))->length);
    }

    public function testBitsIsAnUnsignedBigint(): void
    {
        self::assertEquals(Domain::integer(Field::LongLong, 21, true), (new Numbers())->bits());
    }

    public function testTruthIsAOneDigitBigint(): void
    {
        self::assertEquals(Domain::integer(Field::LongLong, 1), (new Numbers())->truth());
    }

    public function testNumericReadsAHexadecimalLiteralAsTheUnsignedIntegerItsBytesSpell(): void
    {
        $numbers = new Numbers();

        self::assertEquals(Domain::integer(Field::LongLong, 5, true), $numbers->numeric(new RadixLiteral(Radix::Hexadecimal, 'FFFF'), Domain::string(2, Collation::binary())));
        self::assertEquals(Domain::integer(Field::LongLong, 5, true), $numbers->numeric(new Grouped(new Grouped(new RadixLiteral(Radix::Hexadecimal, 'FFFF'))), Domain::string(2, Collation::binary())));
        self::assertEquals(Domain::integer(Field::LongLong, 20, true), $numbers->numeric(new RadixLiteral(Radix::Hexadecimal, '010203040506070809'), Domain::string(9, Collation::binary())));
    }

    public function testNumericReadsABitLiteralOneDigitWider(): void
    {
        self::assertEquals(Domain::integer(Field::LongLong, 4, true), (new Numbers())->numeric(new RadixLiteral(Radix::Bit, '11111111'), Domain::string(1, Collation::binary())));
    }

    public function testNumericKeepsAnyOtherOperand(): void
    {
        $numbers = new Numbers();
        $string = Domain::string(2, Collation::binary());
        $integer = Domain::integer(Field::LongLong, 3);

        self::assertSame($string, $numbers->numeric(new RadixLiteral(Radix::Hexadecimal, 'FFFF', new Name('latin1')), $string));
        self::assertSame($string, $numbers->numeric(new NumberLiteral('12'), $string));
        self::assertSame($integer, $numbers->numeric(new RadixLiteral(Radix::Hexadecimal, 'FF'), $integer));
    }

    public function testBinaryBitsIsABinaryStringAsLongAsItsLongestOperand(): void
    {
        $numbers = new Numbers();

        self::assertEquals(Domain::string(5, Collation::binary()), $numbers->binaryBits(ArithmeticOperator::BitAnd, Domain::string(3, Collation::binary()), Domain::string(5, Collation::binary())));
        self::assertEquals(Domain::string(5, Collation::binary()), $numbers->binaryBits(ArithmeticOperator::BitXor, Domain::string(5, Collation::binary()), Domain::string(3, Collation::binary())));
        self::assertEquals(Domain::string(4, Collation::binary()), $numbers->binaryBits(null, Domain::string(4, Collation::binary())));
    }

    public function testBinaryBitsOfAShiftIsAsLongAsItsLeftOperand(): void
    {
        $numbers = new Numbers();

        self::assertEquals(Domain::string(3, Collation::binary()), $numbers->binaryBits(ArithmeticOperator::ShiftLeft, Domain::string(3, Collation::binary()), Domain::string(5, Collation::binary())));
        self::assertEquals(Domain::string(3, Collation::binary()), $numbers->binaryBits(ArithmeticOperator::ShiftRight, Domain::string(3, Collation::binary()), Domain::string(5, Collation::binary())));
    }

    public function testLegacyNegatedCountsTheSignOfAnExactResult(): void
    {
        $numbers = new Numbers();

        self::assertSame([12, 5, 23], [$numbers->legacyNegated(Domain::integer(Field::LongLong, 11))->length, $numbers->legacyNegated(Domain::decimal(2, 1))->length, $numbers->legacyNegated(Domain::double(23))->length]);
    }

    public function testSignedCountsTheSignOfAnIntegerLiteralOperand(): void
    {
        $numbers = new Numbers();

        self::assertSame([2, 2, 11], [$numbers->signed(new NumberLiteral('1'), Domain::integer(Field::LongLong, 1))->length, $numbers->signed(new Grouped(new NumberLiteral('1')), Domain::integer(Field::LongLong, 1))->length, $numbers->signed(new StringLiteral(['1']), Domain::integer(Field::Long, 11))->length]);
    }
}
