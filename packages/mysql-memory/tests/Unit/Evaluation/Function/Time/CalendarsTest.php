<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Function\Time;

use MySqlMemory\Error\SqlError;
use MySqlMemory\Evaluation\Context;
use MySqlMemory\Evaluation\Frame;
use MySqlMemory\Evaluation\Function\Time\Calendars;
use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use MySqlMemory\Session\Diagnostics;
use MySqlMemory\Session\SqlModes;
use MySqlMemory\Session\Variables;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(Calendars::class)]
#[Small]
final class CalendarsTest extends TestCase
{
    public function testRoutinesNamesTheFunctionsOfDaysWeeksAndMonths(): void
    {
        self::assertSame(['TO_DAYS', 'TO_SECONDS', 'FROM_DAYS', 'DATEDIFF', 'MAKEDATE', 'LAST_DAY', 'DAYNAME', 'MONTHNAME', 'WEEK', 'WEEKOFYEAR', 'YEARWEEK', 'ADDDATE', 'SUBDATE', 'PERIOD_ADD', 'PERIOD_DIFF'], array_map(static fn ($routine): string => $routine->name, (new Calendars())->routines()));
    }

    public function testDaysCountsFromTheFirstDayOfYearZero(): void
    {
        $session = (new Instance())->connect();

        $reply = $session->query("SELECT TO_DAYS('2024-01-01'), TO_DAYS('0000-01-01'), TO_DAYS('2024-01-00')")[0];
        self::assertInstanceOf(ResultSet::class, $reply);
        self::assertEquals([[739251, 1, null]], $reply->rows);
    }

    public function testSecondsCountsTheSecondsOfTheDaysAndTheTime(): void
    {
        $session = (new Instance())->connect();

        $reply = $session->query("SELECT TO_SECONDS('2024-01-01 10:00:00.9')")[0];
        self::assertInstanceOf(ResultSet::class, $reply);
        self::assertEquals([[63871322400]], $reply->rows);
    }

    public function testFromDaysAnswersTheZeroDateOutsideTheCalendarAndNullPastIt(): void
    {
        $session = (new Instance())->connect();

        $reply = $session->query('SELECT FROM_DAYS(365), FROM_DAYS(366), FROM_DAYS(3652424), FROM_DAYS(3652425), FROM_DAYS(3652500)')[0];
        self::assertInstanceOf(ResultSet::class, $reply);
        self::assertEquals([['0000-00-00', '0001-01-01', '9999-12-31', null, '0000-00-00']], $reply->rows);
        $reply = $session->query('SHOW WARNINGS')[0];
        self::assertInstanceOf(ResultSet::class, $reply);
        self::assertEquals([['Warning', 1441, 'Datetime function: from_days field overflow']], $reply->rows);
    }

    public function testDifferenceCountsDaysWithoutTheTime(): void
    {
        $session = (new Instance())->connect();

        $reply = $session->query("SELECT DATEDIFF('2024-01-01 23:59:59', '2024-01-02 00:00:01')")[0];
        self::assertInstanceOf(ResultSet::class, $reply);
        self::assertEquals([[-1]], $reply->rows);
    }

    public function testMakeReadsTwoDigitYearsAndRefusesDaysBelowOne(): void
    {
        $session = (new Instance())->connect();

        $reply = $session->query('SELECT MAKEDATE(70, 1), MAKEDATE(69, 1), MAKEDATE(2024, 0), MAKEDATE(9999, 366), MAKEDATE(2024.5, 1.5)')[0];
        self::assertInstanceOf(ResultSet::class, $reply);
        self::assertEquals([['1970-01-01', '2069-01-01', null, null, '2025-01-02']], $reply->rows);
    }

    public function testLastDayTakesAZeroDayButNoZeroMonth(): void
    {
        $session = (new Instance())->connect();

        $reply = $session->query("SELECT LAST_DAY('2024-02-01 10:00:00'), LAST_DAY('2024-01-00'), LAST_DAY('2024-00-01')")[0];
        self::assertInstanceOf(ResultSet::class, $reply);
        self::assertEquals([['2024-02-29', '2024-01-31', null]], $reply->rows);
    }

    public function testDayNameWritesTheNameInTheLocaleOfTheSession(): void
    {
        $session = (new Instance())->connect();
        $session->query("SET lc_time_names = 'de_DE'");

        $reply = $session->query("SELECT DAYNAME('2024-02-29')")[0];
        self::assertInstanceOf(ResultSet::class, $reply);
        self::assertEquals([['Donnerstag']], $reply->rows);
    }

    public function testMonthNameIsNullForAZeroMonth(): void
    {
        $session = (new Instance())->connect();

        $reply = $session->query("SELECT MONTHNAME('2024-02-29'), MONTHNAME('2024-00-01')")[0];
        self::assertInstanceOf(ResultSet::class, $reply);
        self::assertEquals([['February', null]], $reply->rows);
    }

    public function testLocaleReadsLcTimeNames(): void
    {
        $instance = new Instance();
        $frame = new Frame(new Context(new SqlModes([]), new Diagnostics(), new Variables($instance->catalog, $instance->globals), 0.0));

        self::assertSame('en_US', (new Calendars())->locale($frame)->name);
    }

    public function testWeekCountsInEachModeAndWritesTheYearForYearweek(): void
    {
        $session = (new Instance())->connect();

        $reply = $session->query("SELECT WEEK('2024-01-01'), WEEK('2024-01-01', 9), WEEK('2024-12-31', 1), WEEK('2024-12-31', 3), YEARWEEK('2024-01-01', NULL), YEARWEEK('2024-12-31', 3), YEARWEEK('0000-01-01', 1)")[0];
        self::assertInstanceOf(ResultSet::class, $reply);
        self::assertEquals([[0, 1, 53, 1, 202353, 202501, 4294967248]], $reply->rows);
    }

    public function testWeekOfYearIsTheIsoWeek(): void
    {
        $session = (new Instance())->connect();

        $reply = $session->query("SELECT WEEKOFYEAR('2021-01-03')")[0];
        self::assertInstanceOf(ResultSet::class, $reply);
        self::assertEquals([[53]], $reply->rows);
    }

    public function testPeriodAddCountsMonthsInUnsignedArithmetic(): void
    {
        $session = (new Instance())->connect();

        $reply = $session->query('SELECT PERIOD_ADD(202401, 1), PERIOD_ADD(7001, -1), PERIOD_ADD(202401, -24288), PERIOD_ADD(202401, -24289)')[0];
        self::assertInstanceOf(ResultSet::class, $reply);
        self::assertEquals([[202402, 196912, 0, 6148914691236517176]], $reply->rows);
    }

    public function testPeriodDifferenceRefusesAPeriodWithoutAMonth(): void
    {
        $session = (new Instance())->connect();

        $reply = $session->query('SELECT PERIOD_DIFF(7001, 6912)')[0];
        self::assertInstanceOf(ResultSet::class, $reply);
        self::assertEquals([[-1199]], $reply->rows);
        $this->expectException(SqlError::class);
        $this->expectExceptionMessage('Incorrect arguments to period_diff');
        $session->query('SELECT PERIOD_DIFF(202413, 1)');
    }

    public function testMonthsCountsFromYearZero(): void
    {
        self::assertSame('24288', (new Calendars())->months(202401, 'period_add'));
    }

    public function testSignedReadsTheBitsOfAnUnsignedNumber(): void
    {
        self::assertSame([-1, 5], [(new Calendars())->signed('18446744073709551615'), (new Calendars())->signed('5')]);
    }

    public function testLegacyTellsMySql57(): void
    {
        $instance = new Instance('5.7.44');
        $frame = new Frame(new Context($instance->connect()->modes(), new Diagnostics(), new Variables($instance->catalog, $instance->globals), 0.0));

        self::assertTrue((new Calendars())->legacy($frame));
    }

    public function testLegacyMonthsCountsInThirtyTwoBitsWithoutCheckingTheMonth(): void
    {
        self::assertSame(['24300', '0', '515396158'], [(new Calendars())->legacyMonths(202413), (new Calendars())->legacyMonths(0), (new Calendars())->legacyMonths(4294967295)]);
    }

    public function testPeriodAddOfMySql57(): void
    {
        $session = (new Instance('5.7.44'))->connect();
        $reply = $session->query('SELECT PERIOD_ADD(0, 1), PERIOD_ADD(202413, 1), PERIOD_ADD(-1, 1), PERIOD_DIFF(202401, 13), FROM_DAYS(3652425)')[0];
        self::assertInstanceOf(ResultSet::class, $reply);

        self::assertEquals([[0, 202502, 18611525008, 276, '10000-01-01']], $reply->rows);
    }

    public function testRoutinesMoveADateByANumberOfDays(): void
    {
        $session = (new Instance())->connect();
        $reply = $session->query("SELECT ADDDATE('2024-02-29', 1), SUBDATE('2024-01-01', '1x'), ADDDATE('2024-02-29', 1.5)")[0];
        self::assertInstanceOf(ResultSet::class, $reply);

        self::assertSame([['2024-03-01', '2023-12-31', '2024-03-02']], $reply->rows);
    }
}
