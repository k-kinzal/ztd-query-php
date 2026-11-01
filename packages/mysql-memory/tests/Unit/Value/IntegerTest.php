<?php

declare(strict_types=1);

namespace Tests\Unit\Value;

use MySqlMemory\Value\Integer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(Integer::class)]
#[Small]
final class IntegerTest extends TestCase
{
    public function testTextWritesAnUnsignedIntAboveTheSignedRange(): void
    {
        self::assertSame(['18446744073709551615', '-1', '9223372036854775808'], [Integer::text(-1, true), Integer::text(-1, false), Integer::text(PHP_INT_MIN, true)]);
    }

    public function testRealReadsAnUnsignedIntAboveTheSignedRange(): void
    {
        self::assertSame([1.8446744073709552e19, -1.0, 5.0], [Integer::real(-1, true), Integer::real(-1, false), Integer::real(5, true)]);
    }

    public function testFromUnsignedTextHoldsAValueAboveTheSignedRangeInTheSameBits(): void
    {
        self::assertSame([-1, PHP_INT_MIN, 5], [Integer::fromUnsignedText('18446744073709551615'), Integer::fromUnsignedText('9223372036854775808'), Integer::fromUnsignedText('5')]);
    }

    public function testSignedRangeHoldsForTheBoundsOfBigint(): void
    {
        self::assertSame([true, true, false, false], [Integer::signedRange('-9223372036854775808'), Integer::signedRange('9223372036854775807'), Integer::signedRange('9223372036854775808'), Integer::signedRange('-9223372036854775809')]);
    }

    public function testUnsignedRangeHoldsForTheBoundsOfBigintUnsigned(): void
    {
        self::assertSame([true, true, false, false], [Integer::unsignedRange('0'), Integer::unsignedRange('18446744073709551615'), Integer::unsignedRange('-1'), Integer::unsignedRange('18446744073709551616')]);
    }

    public function testCompareReadsEachSideAsSignedOrUnsigned(): void
    {
        self::assertSame([1, -1, -1, 1, -1, 0], [Integer::compare(-1, true, 1, true), Integer::compare(-1, false, -1, true), Integer::compare(5, false, -1, true), Integer::compare(-1, true, 5, false), Integer::compare(2, false, 3, true), Integer::compare(7, true, 7, false)]);
    }

    public function testFromRealRoundsHalfToEven(): void
    {
        self::assertSame([2, 4, -2], [Integer::fromReal(2.5, false), Integer::fromReal(3.5, false), Integer::fromReal(-2.5, false)]);
    }

    public function testFromRealSaturatesAtTheBoundsOfTheRange(): void
    {
        self::assertSame([PHP_INT_MAX, PHP_INT_MIN, -1, 0], [Integer::fromReal(1e30, false), Integer::fromReal(-1e30, false), Integer::fromReal(1e30, true), Integer::fromReal(-1.0, true)]);
    }

    public function testFromRealHoldsAnUnsignedValueAboveTheSignedRangeInTheSameBits(): void
    {
        self::assertSame(-446744073709551616, Integer::fromReal(1.8e19, true));
    }
}
