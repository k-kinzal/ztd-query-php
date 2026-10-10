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

    public function testParseDateTimeReadsAYearOfOtherThanTwoDigitsAsWritten(): void
    {
        self::assertSame([[5, 1, 1, 0, 0, 0, 0, false], [70, 1, 1, 0, 0, 0, 0, false]], [Temporal::parseDateTime('5-1-1'), Temporal::parseDateTime('070-1-1')]);
    }

    public function testScanDateTimeKeepsTheFractionalDigitsAndTheRest(): void
    {
        self::assertSame([[2024, 1, 1, 1, 2, 3, '5', true, 'x'], [2024, 1, 1, 10, 0, 0, '9999995', true, ''], [2024, 1, 1, 0, 0, 0, '', false, 'x']], [Temporal::scanDateTime('2024-1-1 1:2:3.5x'), Temporal::scanDateTime('2024-01-01 10:00:00.9999995'), Temporal::scanDateTime('2024-01-01x')]);
    }

    public function testScanDateTimeInATimeContextNeedsWhitespaceBeforeTheTimeAndTwelveDigits(): void
    {
        self::assertSame([[2024, 2, 29, 0, 0, 0, '', false, 'T10:11:12'], null, [2024, 2, 29, 10, 11, 12, '', true, '']], [Temporal::scanDateTime('2024-02-29T10:11:12', true), Temporal::scanDateTime('2024022910', true), Temporal::scanDateTime('240229101112', true)]);
    }

    public function testScanDateTimeAnswersNullForDigitsFollowedByText(): void
    {
        self::assertNull(Temporal::scanDateTime('20240101x'));
    }

    public function testScanTimeKeepsTheFractionalDigitsAndTheRest(): void
    {
        self::assertSame([[false, 10, 11, 12, '9999995', 'x'], [true, 34, 0, 0, '', ''], [false, 0, 20, 24, '', '-02-29'], null], [Temporal::scanTime('10:11:12.9999995x'), Temporal::scanTime('-1 10:00'), Temporal::scanTime('2024-02-29'), Temporal::scanTime('abc')]);
    }

    public function testMicroRoundsHalfUpAtTheSeventhDigitOrTruncates(): void
    {
        self::assertSame([123457, 123456, 1000000, 999999, 500000], [Temporal::micro('1234565', false), Temporal::micro('1234565', true), Temporal::micro('9999995', false), Temporal::micro('9999995', true), Temporal::micro('5', false)]);
    }

    public function testScaleRoundsHalfUpToTheDecimalsOrTruncates(): void
    {
        self::assertSame([124000, 123000, 1000000, 900000, 5], [Temporal::scale(123789, 3, false), Temporal::scale(123789, 3, true), Temporal::scale(950000, 1, false), Temporal::scale(987000, 1, true), Temporal::scale(5, 6, false)]);
    }

    public function testAcceptedFollowsTheZeroDateModes(): void
    {
        self::assertSame([true, false, true, false, false, true], [Temporal::accepted(0, 0, 0, false, true), Temporal::accepted(0, 0, 0, true, false), Temporal::accepted(2024, 0, 1, true, false), Temporal::accepted(2024, 0, 1, false, true), Temporal::accepted(2024, 2, 30, false, false), Temporal::accepted(2024, 2, 29, true, true)]);
    }

    public function testLiteralAnswersTheValueOfEachForm(): void
    {
        self::assertSame(['2024-02-29', '-34:00:00.0', '2025-01-01 00:00:00.000000', '10:00:00.123457'], [Temporal::literal('DATE', '2024-02-29', 0, true, true), Temporal::literal('TIME', '-1 10:00:00.0', 1, true, true), Temporal::literal('DATETIME', '2024-12-31 23:59:59.9999995', 6, true, true), Temporal::literal('TIME', '10:00:00.1234567', 6, true, true)]);
    }

    public function testLiteralRefusesTextThatIsNoValueOfTheForm(): void
    {
        self::assertSame([null, null, null, null, null, null, null], [Temporal::literal('TIME', '25:61:00', 0, true, true), Temporal::literal('TIME', '838:59:59.5', 1, true, true), Temporal::literal('DATE', '2024-01-01 10:00:00', 0, true, true), Temporal::literal('DATETIME', '2024-01-01', 0, true, true), Temporal::literal('DATE', '0000-00-00', 0, true, true), Temporal::literal('DATETIME', '9999-12-31 23:59:59.9999999', 6, true, true), Temporal::literal('TIME', '10:00:00x', 0, true, true)]);
    }

    public function testOnDayPutsATimeOnTheDayOfAnInstant(): void
    {
        self::assertSame([[1970, 1, 2, 10, 0, 0, 500000], [1970, 1, 1, 13, 59, 59, 500000]], [Temporal::onDay(86400.0, false, 10, 0, 0, 500000), Temporal::onDay(86400.0, true, 10, 0, 0, 500000)]);
    }

    public function testCarryCarriesAWholeSecondIntoTheDate(): void
    {
        self::assertSame([[2025, 1, 1, 0, 0, 0, 0], [2024, 2, 29, 0, 0, 0, 0], [2024, 1, 1, 10, 0, 0, 5], null], [Temporal::carry(2024, 12, 31, 23, 59, 59, 1000000), Temporal::carry(2024, 2, 28, 23, 59, 59, 1000000), Temporal::carry(2024, 1, 1, 10, 0, 0, 5), Temporal::carry(9999, 12, 31, 23, 59, 59, 1000000)]);
    }
}
