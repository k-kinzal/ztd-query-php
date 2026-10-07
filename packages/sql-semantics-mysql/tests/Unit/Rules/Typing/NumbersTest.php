<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Typing;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Rules\Typing\Numbers;
use SqlSemantics\Platform\MySql\Statement\Expression\Operator\ArithmeticOperator;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Domain;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind;

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

    public function testBitsAndTruthAreIntegers(): void
    {
        self::assertEquals(Domain::integer(Field::LongLong, 21, true), (new Numbers())->bits());
        self::assertEquals(Domain::integer(Field::LongLong, 1), (new Numbers())->truth());
    }
}
