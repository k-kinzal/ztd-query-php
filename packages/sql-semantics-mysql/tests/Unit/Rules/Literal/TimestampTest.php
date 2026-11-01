<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Literal;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Rules\Literal\Timestamp;

#[CoversClass(Timestamp::class)]
#[Small]
final class TimestampTest extends TestCase
{
    public function testPartsRetainsCalendarFieldsFractionAndDisplacement(): void
    {
        self::assertSame([2024, 1, 2, 3, 4, 5, '1234567', 19800], Timestamp::parts('2024-01-02 03:04:05.1234567+05:30'));
        self::assertSame([0, 0, 0, 0, 0, 0, '', null], Timestamp::parts('000000000000'));
        self::assertNull(Timestamp::parts('2024-01-02 03:04:05-00:00'));
        self::assertNull(Timestamp::parts('2024-01-02 03:04:05+14:01'));
        self::assertSame([2024, 1, 2, 3, 4, 0, '', null], Timestamp::parts('2024-01-02+03:04'));
        self::assertSame([2024, 1, 2, 3, 4, 5, '', null], Timestamp::parts('2024-01-02 03+04:05'));
        self::assertNull(Timestamp::parts('2024-01-02 03:04+05:06'));
    }

    public function testCompactSelectsYearWidthBeforeReadingShortClockFields(): void
    {
        self::assertSame(['24', '01', '02', '03', '0', '0', ''], Timestamp::compact('240102030'));
        self::assertSame(['2024', '01', '02', '03', '04', '05', ''], Timestamp::compact('20240102T030405'));
        self::assertNull(Timestamp::compact('2024010203040'));
        self::assertNull(Timestamp::compact('240102T0'));
    }

    public function testDelimitedAcceptsPunctuationButRejectsAlphabeticAndClockWhitespaceSeparators(): void
    {
        self::assertSame(['2024', '01', '02', '03', '04', '05', ''], Timestamp::delimited('2024.01.0203:04:05'));
        self::assertSame(['2024', '01', '02', '03', '04', '05', ''], Timestamp::delimited('2024--01--02 03::04::05'));
        self::assertNull(Timestamp::delimited('2024X01X02 03:04:05'));
        self::assertNull(Timestamp::delimited('2024-01-02 03 04 05'));
    }

    public function testValidExcludesImpossibleValuesButKeepsModeDependentCalendars(): void
    {
        self::assertTrue(Timestamp::valid('2024-02-31 03:04:05'));
        self::assertTrue(Timestamp::valid('0000-00-00 00:00:00'));
        self::assertFalse(Timestamp::valid('2024-01-02'));
        self::assertFalse(Timestamp::valid('0000-00-00 00:00:00+01:00'));
        self::assertFalse(Timestamp::valid('0000-00-00 00:00:00.9999999'));
        self::assertFalse(Timestamp::valid('9999-12-31 23:59:59.9999999'));
    }

    public function testValueAppliesCalendarModesBeforeRounding(): void
    {
        self::assertSame('2024-02-31 03:04:05', Timestamp::value('2024-02-31 03:04:05', 0, false, false, true));
        self::assertNull(Timestamp::value('2024-02-31 03:04:05', 0, false, false, false));
        self::assertNull(Timestamp::value('0000-00-00 00:00:00', 0, true, true, true));
        self::assertSame('2025-01-01 00:00:00.000000', Timestamp::value('2024-12-31 23:59:59.9999999', 6, false, false, false));
    }
}
