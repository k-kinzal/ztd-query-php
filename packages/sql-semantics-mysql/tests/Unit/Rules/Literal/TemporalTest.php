<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Literal;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Rules\Literal\Temporal;

#[CoversClass(Temporal::class)]
#[Small]
final class TemporalTest extends TestCase
{
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

    public function testAcceptedFollowsTheZeroDateModes(): void
    {
        self::assertSame([true, false, true, false, false, true], [Temporal::accepted(0, 0, 0, false, true), Temporal::accepted(0, 0, 0, true, false), Temporal::accepted(2024, 0, 1, true, false), Temporal::accepted(2024, 0, 1, false, true), Temporal::accepted(2024, 2, 30, false, false), Temporal::accepted(2024, 2, 29, true, true)]);
    }

    public function testLiteralPreservesBoundedInvalidDatesOnlyWhenTheModeAllowsThem(): void
    {
        self::assertSame([null, '2024-02-31', '2024-02-31 01:02:03', null, null], [Temporal::literal('DATE', '2024-02-31', 0, false, false), Temporal::literal('DATE', '2024-02-31', 0, false, false, true), Temporal::literal('DATETIME', '2024-02-31 01:02:03', 0, false, false, true), Temporal::literal('DATE', '2024-13-01', 0, false, false, true), Temporal::literal('DATETIME', '2024-02-31 23:59:59.9999999', 6, false, false, true)]);
    }

    public function testTimeLiteralChecksTheClockRangeAndPreservesNegativeZero(): void
    {
        self::assertSame(['-00:00:00', '00:00:01.5', null, null], [Temporal::timeLiteral('-0', 0), Temporal::timeLiteral('1.5', 1), Temporal::timeLiteral('838:59:59.1', 1), Temporal::timeLiteral('1:60:00', 0)]);
    }

    public function testLiteralAnswersTheValueOfEachForm(): void
    {
        self::assertSame(['2024-02-29', '-34:00:00.0', '2025-01-01 00:00:00.000000', '10:00:00.123457'], [Temporal::literal('DATE', '2024-02-29', 0, true, true), Temporal::literal('TIME', '-1 10:00:00.0', 1, true, true), Temporal::literal('DATETIME', '2024-12-31 23:59:59.9999995', 6, true, true), Temporal::literal('TIME', '10:00:00.1234567', 6, true, true)]);
    }

    public function testLiteralRefusesTextThatIsNoValueOfTheForm(): void
    {
        self::assertSame([null, null, null, null, null, null, null], [Temporal::literal('TIME', '25:61:00', 0, true, true), Temporal::literal('TIME', '838:59:59.5', 1, true, true), Temporal::literal('DATE', '2024-01-01 10:00:00', 0, true, true), Temporal::literal('DATETIME', '2024-01-01', 0, true, true), Temporal::literal('DATE', '0000-00-00', 0, true, true), Temporal::literal('DATETIME', '9999-12-31 23:59:59.9999999', 6, true, true), Temporal::literal('TIME', '10:00:00x', 0, true, true)]);
    }

    public function testCarryCarriesAWholeSecondIntoTheDate(): void
    {
        self::assertSame([[2025, 1, 1, 0, 0, 0, 0], [2024, 2, 29, 0, 0, 0, 0], [2024, 1, 1, 10, 0, 0, 5], null], [Temporal::carry(2024, 12, 31, 23, 59, 59, 1000000), Temporal::carry(2024, 2, 28, 23, 59, 59, 1000000), Temporal::carry(2024, 1, 1, 10, 0, 0, 5), Temporal::carry(9999, 12, 31, 23, 59, 59, 1000000)]);
    }
}
