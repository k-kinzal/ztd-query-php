<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation;

use MySqlMemory\Evaluation\Context;
use MySqlMemory\Evaluation\Convert;
use MySqlMemory\Instance;
use MySqlMemory\Typing\Domain;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind;

#[CoversClass(Convert::class)]
#[Small]
final class ConvertTest extends TestCase
{
    public function testToDoubleReadsAnUnsignedIntegerAboveTheSignedRange(): void
    {
        $session = (new Instance())->connect();
        $context = new Context($session->modes(), $session->diagnostics, $session->variables, 0.0);

        self::assertSame(18446744073709551615.0, Convert::toDouble(-1, Domain::integer(Field::LongLong, 20, true), $context));
    }

    public function testToDoubleReadsTheNumberAtTheStartOfAStringAndWarns(): void
    {
        $session = (new Instance())->connect();
        $context = new Context($session->modes(), $session->diagnostics, $session->variables, 0.0);

        self::assertSame(1.5, Convert::toDouble('1.5abc', Domain::string(10, Collation::known('utf8mb4_0900_ai_ci')), $context));
        self::assertSame([['Warning', 1292, "Truncated incorrect DOUBLE value: '1.5abc'"]], $session->diagnostics->conditions);
    }

    public function testToDoubleReadsTheBytesOfABitValueAsAnUnsignedInteger(): void
    {
        $session = (new Instance())->connect();
        $context = new Context($session->modes(), $session->diagnostics, $session->variables, 0.0);

        self::assertSame(256.0, Convert::toDouble("\x01\x00", new Domain(Kind::Bit, Field::Bit, 16), $context));
    }

    public function testToDoubleReadsAHexadecimalLiteralAsTheIntegerOfItsBytes(): void
    {
        $session = (new Instance())->connect();
        $context = new Context($session->modes(), $session->diagnostics, $session->variables, 0.0);

        self::assertSame(65.0, Convert::toDouble('A', Domain::string(1, Collation::binary())->withNumericBytes(), $context));
        self::assertSame([], $session->diagnostics->conditions);
    }

    public function testToDoubleReadsADateAsTheNumberOfItsDigits(): void
    {
        $session = (new Instance())->connect();
        $context = new Context($session->modes(), $session->diagnostics, $session->variables, 0.0);

        self::assertSame(20240229.0, Convert::toDouble('2024-02-29', new Domain(Kind::Date, Field::Date, 10), $context));
    }

    public function testToDoubleKeepsNull(): void
    {
        $session = (new Instance())->connect();
        $context = new Context($session->modes(), $session->diagnostics, $session->variables, 0.0);

        self::assertNull(Convert::toDouble(null, Domain::integer(), $context));
    }

    public function testToIntegerRoundsADoubleHalfToEven(): void
    {
        $session = (new Instance())->connect();
        $context = new Context($session->modes(), $session->diagnostics, $session->variables, 0.0);

        self::assertSame([2, 4], [Convert::toInteger(2.5, Domain::double(), $context), Convert::toInteger(3.5, Domain::double(), $context)]);
    }

    public function testToIntegerRoundsADecimalHalfAwayFromZero(): void
    {
        $session = (new Instance())->connect();
        $context = new Context($session->modes(), $session->diagnostics, $session->variables, 0.0);

        self::assertSame([3, -3], [Convert::toInteger('2.5', Domain::decimal(2, 1), $context), Convert::toInteger('-2.5', Domain::decimal(2, 1), $context)]);
    }

    public function testToIntegerSaturatesANegativeDoubleAtZeroForAnUnsignedTarget(): void
    {
        $session = (new Instance())->connect();
        $context = new Context($session->modes(), $session->diagnostics, $session->variables, 0.0);

        self::assertSame(0, Convert::toInteger(-1.0, Domain::double(), $context, true));
    }

    public function testToIntegerReadsTheIntegerAtTheStartOfAStringAndWarns(): void
    {
        $session = (new Instance())->connect();
        $context = new Context($session->modes(), $session->diagnostics, $session->variables, 0.0);

        self::assertSame(12, Convert::toInteger('12abc', Domain::string(10, Collation::known('utf8mb4_0900_ai_ci')), $context));
        self::assertSame([['Warning', 1292, "Truncated incorrect INTEGER value: '12abc'"]], $session->diagnostics->conditions);
    }

    public function testToIntegerReadsATimeAsTheNumberOfItsDigits(): void
    {
        $session = (new Instance())->connect();
        $context = new Context($session->modes(), $session->diagnostics, $session->variables, 0.0);

        self::assertSame(103015, Convert::toInteger('10:30:15', new Domain(Kind::Time, Field::Time, 10), $context));
    }

    public function testToDecimalWritesAnUnsignedIntegerAboveTheSignedRange(): void
    {
        $session = (new Instance())->connect();
        $context = new Context($session->modes(), $session->diagnostics, $session->variables, 0.0);

        self::assertSame('18446744073709551615', Convert::toDecimal(-1, Domain::integer(Field::LongLong, 20, true), $context));
    }

    public function testToDecimalReadsADoubleAsItsShortestDecimal(): void
    {
        $session = (new Instance())->connect();
        $context = new Context($session->modes(), $session->diagnostics, $session->variables, 0.0);

        self::assertSame('0.1', Convert::toDecimal(0.1, Domain::double(), $context));
    }

    public function testToDecimalReadsTheNumberAtTheStartOfAStringAndWarns(): void
    {
        $session = (new Instance())->connect();
        $context = new Context($session->modes(), $session->diagnostics, $session->variables, 0.0);

        self::assertSame('3.25', Convert::toDecimal('3.25x', Domain::string(10, Collation::known('utf8mb4_0900_ai_ci')), $context));
        self::assertSame([['Warning', 1292, "Truncated incorrect DECIMAL value: '3.25x'"]], $session->diagnostics->conditions);
    }

    public function testToDecimalReadsADateTimeWithAFractionAsTheNumberOfItsDigits(): void
    {
        $session = (new Instance())->connect();
        $context = new Context($session->modes(), $session->diagnostics, $session->variables, 0.0);

        self::assertSame('20240229100000.5', Convert::toDecimal('2024-02-29 10:00:00.5', new Domain(Kind::DateTime, Field::DateTime, 21, 1), $context));
    }

    public function testToTextWritesAnUnsignedIntegerAboveTheSignedRange(): void
    {
        self::assertSame('18446744073709551615', Convert::toText(-1, Domain::integer(Field::LongLong, 20, true)));
    }

    public function testToTextWritesADoubleWithAFixedNumberOfDecimals(): void
    {
        self::assertSame('1.50', Convert::toText(1.5, Domain::double(22, 2)));
    }

    public function testToTextWritesALargeDoubleWithAnExponent(): void
    {
        self::assertSame(['1e20', '0.1'], [Convert::toText(1e20, Domain::double()), Convert::toText(0.1, Domain::double())]);
    }

    public function testToTextKeepsTheTextOfADecimalAndNull(): void
    {
        self::assertSame(['1.50', null], [Convert::toText('1.50', Domain::decimal(3, 2)), Convert::toText(null, Domain::decimal(3, 2))]);
    }

    public function testToBoolTellsWhetherANumberIsOtherThanZero(): void
    {
        $session = (new Instance())->connect();
        $context = new Context($session->modes(), $session->diagnostics, $session->variables, 0.0);

        self::assertSame(
            [true, false, false, false],
            [Convert::toBool(5, Domain::integer(), $context), Convert::toBool("\0", new Domain(Kind::Bit, Field::Bit, 1), $context), Convert::toBool(0.0, Domain::double(), $context), Convert::toBool('0.000', Domain::decimal(4, 3), $context)],
        );
    }

    public function testToBoolReadsAStringAsADoubleAndWarns(): void
    {
        $session = (new Instance())->connect();
        $context = new Context($session->modes(), $session->diagnostics, $session->variables, 0.0);
        $domain = Domain::string(10, Collation::known('utf8mb4_0900_ai_ci'));

        self::assertSame([false, true, null], [Convert::toBool('abc', $domain, $context), Convert::toBool('1x', $domain, $context), Convert::toBool(null, $domain, $context)]);
        self::assertSame([['Warning', 1292, "Truncated incorrect DOUBLE value: 'abc'"], ['Warning', 1292, "Truncated incorrect DOUBLE value: '1x'"]], $session->diagnostics->conditions);
    }

    public function testStringNumberReadsTheNumberAtTheStartAndWarnsWithTheKindRead(): void
    {
        $session = (new Instance())->connect();
        $context = new Context($session->modes(), $session->diagnostics, $session->variables, 0.0);

        self::assertSame('12.5e1', Convert::stringNumber(' 12.5e1xyz', 'DOUBLE', $context, false));
        self::assertSame([['Warning', 1292, "Truncated incorrect DOUBLE value: ' 12.5e1xyz'"]], $session->diagnostics->conditions);
    }

    public function testStringNumberDoesNotWarnForACompleteNumber(): void
    {
        $session = (new Instance())->connect();
        $context = new Context($session->modes(), $session->diagnostics, $session->variables, 0.0);

        self::assertSame('12.5', Convert::stringNumber('12.5', 'DECIMAL', $context, true));
        self::assertSame([], $session->diagnostics->conditions);
    }

    public function testStringIntegerSaturatesANegativeOverflowAndWarns(): void
    {
        $session = (new Instance())->connect();
        $context = new Context($session->modes(), $session->diagnostics, $session->variables, 0.0);

        self::assertSame(PHP_INT_MIN, Convert::stringInteger('-99999999999999999999', $context, false));
        self::assertSame([['Warning', 1292, "Truncated incorrect INTEGER value: '-99999999999999999999'"]], $session->diagnostics->conditions);
    }

    public function testStringIntegerHoldsTheLargestUnsignedValueInTheBitsOfAnInt(): void
    {
        $session = (new Instance())->connect();
        $context = new Context($session->modes(), $session->diagnostics, $session->variables, 0.0);

        self::assertSame(-1, Convert::stringInteger('18446744073709551615', $context, true));
        self::assertSame([], $session->diagnostics->conditions);
    }

    public function testExactIntegerSaturatesAtTheBoundsOfTheUnsignedRange(): void
    {
        self::assertSame(
            [0, -1, -1, 5],
            [Convert::exactInteger('-5', true), Convert::exactInteger('18446744073709551616', true), Convert::exactInteger('18446744073709551615', true), Convert::exactInteger('5', true)],
        );
    }

    public function testExactIntegerSaturatesAtTheBoundsOfTheSignedRange(): void
    {
        self::assertSame(
            [PHP_INT_MAX, PHP_INT_MIN, 42],
            [Convert::exactInteger('9223372036854775808', false), Convert::exactInteger('-9223372036854775809', false), Convert::exactInteger('42', false)],
        );
    }

    public function testBitsReadsTheBytesWithTheFirstTheMostSignificant(): void
    {
        self::assertSame([258, 0], [Convert::bits("\x01\x02"), Convert::bits('')]);
    }

    public function testBitsReadsOnlyTheLastEightBytes(): void
    {
        self::assertSame(1, Convert::bits("\x05\x00\x00\x00\x00\x00\x00\x00\x01"));
    }
}
