<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Compile\Family;

use MySqlMemory\Evaluation\Compile\Family\TimestampLiteral;
use MySqlMemory\Session\SqlModes;
use MySqlMemory\Value\Zone;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\GrammarRelease;

#[CoversClass(TimestampLiteral::class)]
#[Small]
final class TimestampLiteralTest extends TestCase
{
    public function testMomentRetainsYearZeroInsteadOfUsingFromDaysSentinels(): void
    {
        self::assertSame([0, 1, 1, 0, 0, 0], TimestampLiteral::moment(\MySqlMemory\Value\Calendar::epoch(0, 1, 1, 0, 0, 0)));
        self::assertSame([-1, 12, 31, 10, 0, 0], TimestampLiteral::moment(\MySqlMemory\Value\Calendar::epoch(0, 1, 1, 0, 0, 0) - 50400));
    }

    public function testValueRefusesAnExplicitZoneOverflowWithItsConvertedFields(): void
    {
        $this->expectExceptionCode(1292);
        $this->expectExceptionMessage("Truncated incorrect temporal value: '0000-01-01 13:59:59'");

        TimestampLiteral::value('9999-12-31 23:59:59-14:00', 0, new SqlModes([]), new Zone('+00:00', 0));
    }

    public function testErrorDistinguishesOffsetConversionFromModeRefusals(): void
    {
        self::assertSame("Truncated incorrect temporal value: '2024-00-01 03:04:05+0:00'", TimestampLiteral::error('2024-00-01 03:04:05+00:00', new SqlModes([]))->getMessage());
        self::assertSame("Incorrect DATETIME value: '2024-00-01 03:04:05+00:00'", TimestampLiteral::error('2024-00-01 03:04:05+00:00', new SqlModes(['NO_ZERO_IN_DATE']))->getMessage());
    }

    public function testValueAppliesDisplacementAndSessionZone(): void
    {
        self::assertSame('2024-01-02 11:04:05.123457', TimestampLiteral::value('2024-01-02 03:04:05.1234567+01:00', 6, new SqlModes([]), new Zone('+09:00', 32400)));
        self::assertSame('2024-01-02 03:04:06.000000', TimestampLiteral::value('2024-01-02 03:04:05.9999999+01:00', 6, new SqlModes([]), Zone::utc()));
        self::assertNull(TimestampLiteral::value('2024-01-02 03:04:05+01:00', 0, new SqlModes([], GrammarRelease::MySql5744), Zone::utc()));
    }

    public function testValuePreservesCalendarModeDependence(): void
    {
        self::assertSame('2024-02-31 03:04:05', TimestampLiteral::value('2024-02-31 03:04:05', 0, new SqlModes(['ALLOW_INVALID_DATES']), Zone::utc()));
        self::assertNull(TimestampLiteral::value('2024-02-31 03:04:05', 0, new SqlModes([]), Zone::utc()));
        self::assertNull(TimestampLiteral::value('0000-00-00 00:00:00', 0, new SqlModes(['NO_ZERO_DATE']), Zone::utc()));
    }
}
