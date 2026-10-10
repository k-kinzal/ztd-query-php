<?php

declare(strict_types=1);

namespace Tests\Unit\Value;

use MySqlMemory\Value\Interval;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Expression\IntervalUnit;

#[CoversClass(Interval::class)]
#[Small]
final class IntervalTest extends TestCase
{
    public function testReadKeepsTheFractionOfSeconds(): void
    {
        $interval = Interval::read('1.5', IntervalUnit::Second);

        self::assertSame([0, 1500000], [$interval?->months, $interval?->microseconds]);
    }

    public function testReadRoundsTheQuantityOfOtherSimpleUnits(): void
    {
        $days = Interval::read('1.5', IntervalUnit::Day);
        $weeks = Interval::read('2', IntervalUnit::Week);
        $minutes = Interval::read('-3', IntervalUnit::Minute);

        self::assertSame([172800000000, 1209600000000, -180000000], [$days?->microseconds, $weeks?->microseconds, $minutes?->microseconds]);
    }

    public function testReadCountsMonthsQuartersAndYearsInMonths(): void
    {
        $months = Interval::read('5', IntervalUnit::Month);
        $quarters = Interval::read('2', IntervalUnit::Quarter);
        $years = Interval::read('-1', IntervalUnit::Year);

        self::assertSame([5, 6, -12, 0], [$months?->months, $quarters?->months, $years?->months, $years?->microseconds]);
    }

    public function testReadReadsTheTextOfACompoundUnit(): void
    {
        $interval = Interval::read('1:30', IntervalUnit::HourMinute);

        self::assertSame([0, 5400000000], [$interval?->months, $interval?->microseconds]);
    }

    public function testCompoundReadsThePartsFromTheLargest(): void
    {
        $interval = Interval::compound('1 2:3:4.5', IntervalUnit::DayMicrosecond);

        self::assertSame([0, 93784500000], [$interval?->months, $interval?->microseconds]);
    }

    public function testCompoundFillsMissingPartsFromTheSmallest(): void
    {
        $interval = Interval::compound('5', IntervalUnit::DaySecond);

        self::assertSame([0, 5000000], [$interval?->months, $interval?->microseconds]);
    }

    public function testCompoundNegatesEveryPartOfANegativeQuantity(): void
    {
        $interval = Interval::compound('-1-2', IntervalUnit::YearMonth);

        self::assertSame([-14, 0], [$interval?->months, $interval?->microseconds]);
    }

    public function testCompoundReadsSixFractionalDigitsAsMicroseconds(): void
    {
        $interval = Interval::compound('1.999999', IntervalUnit::SecondMicrosecond);

        self::assertSame(1999999, $interval?->microseconds);
    }

    public function testCompoundReadsATextWithoutNumbersAsAnEmptyInterval(): void
    {
        $interval = Interval::compound('abc', IntervalUnit::DayHour);

        self::assertSame([0, 0], [$interval?->months, $interval?->microseconds]);
    }

    public function testCompoundAnswersNullForMoreNumbersThanTheUnitHasParts(): void
    {
        self::assertNull(Interval::compound('1 2 3', IntervalUnit::DayHour));
    }

    public function testPartsAnswersThePartsOfACompoundUnitFromTheLargest(): void
    {
        self::assertSame([['year', 'month'], ['day', 'hour', 'minute', 'second', 'micro'], ['second', 'micro'], ['second', 'micro']], [Interval::parts(IntervalUnit::YearMonth), Interval::parts(IntervalUnit::DayMicrosecond), Interval::parts(IntervalUnit::SecondMicrosecond), Interval::parts(IntervalUnit::Day)]);
    }

    public function testDatedHoldsForUnitsOfWholeDays(): void
    {
        self::assertSame([true, true, true, false, false, false], [Interval::dated(IntervalUnit::Day), Interval::dated(IntervalUnit::Week), Interval::dated(IntervalUnit::YearMonth), Interval::dated(IntervalUnit::Hour), Interval::dated(IntervalUnit::DayHour), Interval::dated(IntervalUnit::Second)]);
    }
}
