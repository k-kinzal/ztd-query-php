<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Scalar\Temporal;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Model\Scalar\Temporal\IntervalFields;
use SqlSemantics\Model\Scalar\Temporal\MySqlUnit;

#[CoversClass(IntervalFields::class)]
#[Medium]
final class IntervalFieldsTest extends TestCase
{
    public function testCalendarOnlyIncludesQuarterAndWeek(): void
    {
        self::assertTrue(IntervalFields::calendarOnly(MySqlUnit::Quarter));
        self::assertTrue(IntervalFields::calendarOnly(MySqlUnit::Week));
        self::assertFalse(IntervalFields::calendarOnly(MySqlUnit::DayHour));
    }

    public function testClockOnlyKeepsDateFieldsOutOfTimeOnlyArithmetic(): void
    {
        self::assertTrue(IntervalFields::clockOnly(MySqlUnit::HourMicrosecond));
        self::assertFalse(IntervalFields::clockOnly(MySqlUnit::DayMicrosecond));
    }

    public function testQuantityTypeDistinguishesIntegersFractionsAndCompositeStrings(): void
    {
        self::assertSame('bigint', IntervalFields::quantityType(MySqlUnit::Month));
        self::assertSame('numeric', IntervalFields::quantityType(MySqlUnit::Second));
        self::assertSame('varchar', IntervalFields::quantityType(MySqlUnit::DaySecond));
    }

    #[TestWith([MySqlUnit::Microsecond, 'bigint', false, true])]
    #[TestWith([MySqlUnit::Second, 'numeric', false, true])]
    #[TestWith([MySqlUnit::Minute, 'bigint', false, true])]
    #[TestWith([MySqlUnit::Hour, 'bigint', false, true])]
    #[TestWith([MySqlUnit::Day, 'bigint', true, false])]
    #[TestWith([MySqlUnit::Week, 'bigint', true, false])]
    #[TestWith([MySqlUnit::Month, 'bigint', true, false])]
    #[TestWith([MySqlUnit::Quarter, 'bigint', true, false])]
    #[TestWith([MySqlUnit::Year, 'bigint', true, false])]
    #[TestWith([MySqlUnit::SecondMicrosecond, 'varchar', false, true])]
    #[TestWith([MySqlUnit::MinuteMicrosecond, 'varchar', false, true])]
    #[TestWith([MySqlUnit::MinuteSecond, 'varchar', false, true])]
    #[TestWith([MySqlUnit::HourMicrosecond, 'varchar', false, true])]
    #[TestWith([MySqlUnit::HourSecond, 'varchar', false, true])]
    #[TestWith([MySqlUnit::HourMinute, 'varchar', false, true])]
    #[TestWith([MySqlUnit::DayMicrosecond, 'varchar', false, false])]
    #[TestWith([MySqlUnit::DaySecond, 'varchar', false, false])]
    #[TestWith([MySqlUnit::DayMinute, 'varchar', false, false])]
    #[TestWith([MySqlUnit::DayHour, 'varchar', false, false])]
    #[TestWith([MySqlUnit::YearMonth, 'varchar', true, false])]
    public function testQuantityTypeClassifiesEveryUnit(MySqlUnit $unit, string $type, bool $calendar, bool $clock): void
    {
        self::assertSame($type, IntervalFields::quantityType($unit));
        self::assertSame($calendar, IntervalFields::calendarOnly($unit));
        self::assertSame($clock, IntervalFields::clockOnly($unit));
    }
}
