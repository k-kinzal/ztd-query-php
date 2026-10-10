<?php

declare(strict_types=1);

namespace Tests\Unit\Value;

use MySqlMemory\Value\Real;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(Real::class)]
#[Small]
final class RealTest extends TestCase
{
    public function testFormatWritesPositionalDigitsUpTo15IntegerDigits(): void
    {
        self::assertSame(['100000000000000', '123456789012345', '0.1', '-2.5', '100'], [Real::format(1e14), Real::format(123456789012345.0), Real::format(0.1), Real::format(-2.5), Real::format(100.0)]);
    }

    public function testFormatWritesLargeNumbersWithAnExponent(): void
    {
        self::assertSame(['1e15', '1.2345678901234567e19', '-1e300'], [Real::format(1e15), Real::format(12345678901234567890.0), Real::format(-1e300)]);
    }

    public function testFormatWritesSmallNumbersWithAnExponent(): void
    {
        self::assertSame(['0.000000000000001', '1e-16', '1.2345678901234568e-5'], [Real::format(1e-15), Real::format(1e-16), Real::format(1.2345678901234568e-5)]);
    }

    public function testFormatWritesZero(): void
    {
        self::assertSame('0', Real::format(0.0));
    }

    public function testDigitsAnswersTheShortestDigitsAndThePlaceOfThePoint(): void
    {
        self::assertSame([['1', 0], ['12345', 4], ['17', -2]], [Real::digits(0.1), Real::digits(1234.5), Real::digits(0.0017)]);
    }

    public function testFixedWritesAFixedNumberOfDecimals(): void
    {
        self::assertSame(['3.14', '0.00', '12.500', '-3'], [Real::fixed(3.14159, 2), Real::fixed(-0.001, 2), Real::fixed(12.5, 3), Real::fixed(-3.0, 0)]);
    }

    public function testFormatWritesAnInfinityAsZero(): void
    {
        self::assertSame(['0', '0'], [Real::format(INF), Real::format(-INF)]);
    }

    public function testFormatWritesNegativeZero(): void
    {
        self::assertSame('-0', Real::format(-0.0));
    }
}
