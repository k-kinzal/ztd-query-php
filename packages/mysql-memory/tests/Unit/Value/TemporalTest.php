<?php

declare(strict_types=1);

namespace Tests\Unit\Value;

use MySqlMemory\Value\Temporal;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(Temporal::class)]
#[Small]
final class TemporalTest extends TestCase
{
    public function testParseDateTimeReadsADelimitedDatetime(): void
    {
        self::assertSame([2024, 3, 1, 12, 34, 56, 789000, true], Temporal::parseDateTime(' 2024-03-01 12:34:56.789 '));
    }

    public function testParseDateTimeReadsDigitsWithoutDelimiters(): void
    {
        self::assertSame([[2024, 3, 1, 0, 0, 0, 0, false], [2024, 3, 1, 12, 34, 56, 0, true]], [Temporal::parseDateTime('240301'), Temporal::parseDateTime('20240301123456')]);
    }

    public function testParseDateTimeReadsATwoDigitYear(): void
    {
        self::assertSame([[1999, 1, 2, 0, 0, 0, 0, false], [2069, 12, 31, 0, 0, 0, 0, false], [1970, 1, 1, 0, 0, 0, 0, false]], [Temporal::parseDateTime('99-1-2'), Temporal::parseDateTime('69-12-31'), Temporal::parseDateTime('70-01-01')]);
    }

    public function testParseDateTimeReadsAnyPunctuationBetweenTheParts(): void
    {
        self::assertSame([2024, 3, 1, 1, 2, 3, 0, true], Temporal::parseDateTime('2024/3/1T1:2:3'));
    }

    public function testParseDateTimeAnswersNullForAnotherText(): void
    {
        self::assertSame([null, null], [Temporal::parseDateTime('hello'), Temporal::parseDateTime('2024')]);
    }

    public function testValidHoldsForExistingDatesAndTheZeroDate(): void
    {
        self::assertSame([true, true, true], [Temporal::valid(2024, 2, 29), Temporal::valid(0, 0, 0), Temporal::valid(0, 1, 1)]);
    }

    public function testValidFailsForDatesThatDoNotExist(): void
    {
        self::assertSame([false, false, false, false], [Temporal::valid(2023, 2, 29), Temporal::valid(2024, 0, 1), Temporal::valid(2024, 4, 31), Temporal::valid(10000, 1, 1)]);
    }

    public function testDateWritesFourDigitYears(): void
    {
        self::assertSame('0005-01-02', Temporal::date(5, 1, 2));
    }

    public function testDateTimeWritesTheFractionalDigitsOfTheDecimals(): void
    {
        self::assertSame(['2024-03-01 01:02:03.450', '2024-03-01 01:02:03'], [Temporal::dateTime(2024, 3, 1, 1, 2, 3, 450000, 3), Temporal::dateTime(2024, 3, 1, 1, 2, 3, 450000, 0)]);
    }

    public function testTimeWritesHoursBeyondADay(): void
    {
        self::assertSame(['-838:59:59.000005', '01:02:03'], [Temporal::time(true, 838, 59, 59, 5, 6), Temporal::time(false, 1, 2, 3, 0, 0)]);
    }

    public function testFractionTruncatesToTheDecimals(): void
    {
        self::assertSame(['.12', '', '.000001'], [Temporal::fraction(123456, 2), Temporal::fraction(1, 0), Temporal::fraction(1, 6)]);
    }

    public function testParseTimeReadsDaysHoursMinutesAndSeconds(): void
    {
        self::assertSame([[true, 26, 3, 4, 500000], [false, 12, 34, 0, 0]], [Temporal::parseTime('-1 02:03:04.5'), Temporal::parseTime('12:34')]);
    }

    public function testParseTimeReadsDigitsFromTheSeconds(): void
    {
        self::assertSame([[false, 12, 34, 56, 700000], [false, 0, 0, 56, 0], [false, 1, 0, 0, 0]], [Temporal::parseTime('123456.7'), Temporal::parseTime('56'), Temporal::parseTime('10000')]);
    }

    public function testParseTimeAnswersNullForAnotherText(): void
    {
        self::assertNull(Temporal::parseTime('x'));
    }

    public function testNumberReadsTheDigitsOfADateOrTime(): void
    {
        self::assertSame(['20240301123456.5', '-120000', '1', '20240301'], [Temporal::number('2024-03-01 12:34:56.5'), Temporal::number('-12:00:00'), Temporal::number('00:00:01'), Temporal::number('2024-03-01')]);
    }
}
