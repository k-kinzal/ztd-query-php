<?php

declare(strict_types=1);

namespace Tests\Unit\Value;

use MySqlMemory\Value\Calendar;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(Calendar::class)]
#[Small]
final class CalendarTest extends TestCase
{
    public function testDaysCountsDaysAsToDays(): void
    {
        self::assertSame([728779, 733321, 730485, 366], [Calendar::days(1995, 5, 1), Calendar::days(2007, 10, 7), Calendar::days(2000, 1, 1), Calendar::days(1, 1, 1)]);
    }

    public function testDaysCountsTheLeapDayOfALeapYear(): void
    {
        self::assertSame([60, 59], [Calendar::days(2024, 3, 1) - Calendar::days(2024, 1, 1), Calendar::days(2023, 3, 1) - Calendar::days(2023, 1, 1)]);
    }

    public function testDateAnswersTheDateOfADayNumberAsFromDays(): void
    {
        self::assertSame([[2000, 7, 3], [1995, 5, 1], [2024, 2, 29]], [Calendar::date(730669), Calendar::date(728779), Calendar::date(Calendar::days(2024, 2, 29))]);
    }

    public function testDateAnswersTheZeroDateForTheFirstYear(): void
    {
        self::assertSame([[0, 0, 0], [0, 0, 0]], [Calendar::date(365), Calendar::date(1)]);
    }

    public function testMonthLengthFollowsTheGregorianLeapYears(): void
    {
        self::assertSame([29, 28, 29, 28, 30, 31], [Calendar::monthLength(2024, 2), Calendar::monthLength(1900, 2), Calendar::monthLength(2000, 2), Calendar::monthLength(2023, 2), Calendar::monthLength(2023, 4), Calendar::monthLength(2023, 12)]);
    }

    public function testAddMonthsKeepsTheDayWithinTheMonthReached(): void
    {
        self::assertSame([[2024, 2, 29], [2023, 2, 28], [2022, 12, 15]], [Calendar::addMonths(2024, 1, 31, 1), Calendar::addMonths(2024, 2, 29, -12), Calendar::addMonths(2024, 1, 15, -13)]);
    }

    public function testAddMonthsAnswersNullOutsideYearsZeroTo9999(): void
    {
        self::assertSame([null, null], [Calendar::addMonths(9999, 12, 1, 1), Calendar::addMonths(0, 1, 1, -1)]);
    }

    public function testAddMicrosecondsCarriesIntoTheNextYear(): void
    {
        self::assertSame([2025, 1, 1, 0, 0, 0, 0], Calendar::addMicroseconds(2024, 12, 31, 23, 59, 59, 999999, 1));
    }

    public function testAddMicrosecondsBorrowsFromThePreviousDay(): void
    {
        self::assertSame([2024, 2, 29, 23, 59, 59, 999999], Calendar::addMicroseconds(2024, 3, 1, 0, 0, 0, 0, -1));
    }

    public function testAddMicrosecondsAnswersNullAfterTheYear9999(): void
    {
        self::assertNull(Calendar::addMicroseconds(9999, 12, 31, 23, 59, 59, 0, 1000000));
    }

    public function testEpochCountsSecondsFrom1970(): void
    {
        self::assertSame([0, 1711846800, -1], [Calendar::epoch(1970, 1, 1, 0, 0, 0), Calendar::epoch(2024, 3, 31, 1, 0, 0), Calendar::epoch(1969, 12, 31, 23, 59, 59)]);
    }

    public function testMomentAnswersTheDateAndTimeOfSecondsFrom1970(): void
    {
        self::assertSame([[2024, 3, 31, 1, 0, 0], [3001, 1, 18, 23, 59, 59]], [Calendar::moment(1711846800), Calendar::moment(32536771199)]);
    }
}
