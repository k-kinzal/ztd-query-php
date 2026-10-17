<?php

declare(strict_types=1);

namespace Tests\Unit\Value;

use MySqlMemory\Value\Decimal;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(Decimal::class)]
#[Small]
final class DecimalTest extends TestCase
{
    public function testScaleCountsTheDigitsAfterThePoint(): void
    {
        self::assertSame([3, 0, 1], [Decimal::scale('1.230'), Decimal::scale('12'), Decimal::scale('-0.5')]);
    }

    public function testIntegerDigitsCountsTheDigitsBeforeThePoint(): void
    {
        self::assertSame([2, 1, 1], [Decimal::integerDigits('-0012.5'), Decimal::integerDigits('0.5'), Decimal::integerDigits('7')]);
    }

    public function testRoundRoundsHalfAwayFromZero(): void
    {
        self::assertSame(['3', '-3', '1.24', '-1.24'], [Decimal::round('2.5', 0), Decimal::round('-2.5', 0), Decimal::round('1.235', 2), Decimal::round('-1.235', 2)]);
    }

    public function testRoundPadsToALargerScale(): void
    {
        self::assertSame(['1.23400', '5.00'], [Decimal::round('1.234', 5), Decimal::round('5', 2)]);
    }

    public function testRoundRoundsToTensForANegativeScale(): void
    {
        self::assertSame(['160', '-160', '100', '0'], [Decimal::round('155', -1), Decimal::round('-155', -1), Decimal::round('149', -2), Decimal::round('4', -1)]);
    }

    public function testTruncateCutsTowardZero(): void
    {
        self::assertSame(['-1', '1.9', '100', '-100'], [Decimal::truncate('-1.99', 0), Decimal::truncate('1.99', 1), Decimal::truncate('199', -2), Decimal::truncate('-199', -2)]);
    }

    public function testAddKeepsTheLargerScale(): void
    {
        self::assertSame(['3.75', '0.0'], [Decimal::add('1.5', '2.25'), Decimal::add('-1.5', '1.5')]);
    }

    public function testSubtractKeepsTheLargerScale(): void
    {
        self::assertSame(['-0.50', '1.25'], [Decimal::subtract('1', '1.50'), Decimal::subtract('2', '0.75')]);
    }

    public function testMultiplyAddsTheScales(): void
    {
        self::assertSame(['3.75', '-0.0006'], [Decimal::multiply('1.5', '2.5'), Decimal::multiply('0.02', '-0.03')]);
    }

    public function testDivideRoundsTheQuotientToTheScale(): void
    {
        self::assertSame(['0.3333', '0.6667', '-2.50'], [Decimal::divide('1', '3', 4), Decimal::divide('2', '3', 4), Decimal::divide('5', '-2', 2)]);
    }

    public function testDivideAnswersNullForAZeroDivisor(): void
    {
        self::assertSame([null, null], [Decimal::divide('1', '0', 4), Decimal::divide('1', '0.00', 4)]);
    }

    public function testModuloTakesTheSignOfTheDividend(): void
    {
        self::assertSame(['-1', '1', '1.5'], [Decimal::modulo('-7', '3'), Decimal::modulo('7', '-3'), Decimal::modulo('5.5', '2')]);
    }

    public function testModuloAnswersNullForAZeroDivisor(): void
    {
        self::assertNull(Decimal::modulo('7', '0.0'));
    }

    public function testCompareComparesTheValues(): void
    {
        self::assertSame([0, -1, 1], [Decimal::compare('1.0', '1.00'), Decimal::compare('-1', '0.5'), Decimal::compare('10', '9.99')]);
    }

    public function testNegateWritesNoNegativeZero(): void
    {
        self::assertSame(['0', '1.5', '-2'], [Decimal::negate('0'), Decimal::negate('-1.5'), Decimal::negate('2')]);
    }

    public function testCanonicalDropsThePlusSignLeadingZerosAndNegativeZero(): void
    {
        self::assertSame(['7.50', '0.00', '0', '-0.5', '5'], [Decimal::canonical('+007.50'), Decimal::canonical('-0.00'), Decimal::canonical(''), Decimal::canonical('-.5'), Decimal::canonical('5.')]);
    }

    public function testFromIntegerReadsAnUnsignedInt(): void
    {
        self::assertSame(['18446744073709551615', '-1', '42'], [Decimal::fromInteger(-1, true), Decimal::fromInteger(-1), Decimal::fromInteger(42, true)]);
    }

    public function testFromDoubleWritesTheShortestDigits(): void
    {
        self::assertSame(['0.00000015', '-123.25', '100000000000000000000', '0', '0.1'], [Decimal::fromDouble(1.5e-7), Decimal::fromDouble(-123.25), Decimal::fromDouble(1e20), Decimal::fromDouble(0.0), Decimal::fromDouble(0.1)]);
    }

    public function testNumericAnswersZeroForATextThatIsNoNumber(): void
    {
        self::assertSame(['0', '1.5', '-2'], [Decimal::numeric('abc'), Decimal::numeric('1.5'), Decimal::numeric('-2')]);
    }
}
