<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Operator;

use MySqlMemory\Evaluation\Context;
use MySqlMemory\Evaluation\Operator\Moments;
use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use MySqlMemory\Session\Diagnostics;
use MySqlMemory\Session\SqlModes;
use MySqlMemory\Session\Variables;
use MySqlMemory\Typing\Domain;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(Moments::class)]
#[Small]
final class MomentsTest extends TestCase
{
    public function testConvertReadsNumbersAsDateDigits(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query('SELECT CAST(20240115 AS DATE), CAST(20240115102030 AS DATETIME), CAST(20240115 AS DATETIME)')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['2024-01-15', '2024-01-15 10:20:30', '2024-01-15 00:00:00']], $result->rows);
    }

    public function testConvertMovesBetweenDatesAndDatetimes(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query("SELECT CAST(DATE '2024-01-15' AS DATETIME), CAST('2024-01-15 10:20:30' AS DATE), CAST(TIMESTAMP '2024-01-15 10:20:30' AS DATE)")[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['2024-01-15 00:00:00', '2024-01-15', '2024-01-15']], $result->rows);
    }

    public function testConvertPutsATimeOnTheCurrentDate(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (tm TIME)');
        $session->query("INSERT INTO t VALUES ('12:30:00')");
        $result = $session->query("SELECT CAST(tm AS DATETIME) = CONCAT(CURDATE(), ' 12:30:00') FROM t")[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['1']], $result->rows);
    }

    public function testConvertWarnsForAStringThatIsNoValidDate(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query("SELECT CAST('2024-02-30' AS DATE), CAST('abc' AS DATETIME)")[0];
        $warnings = $session->query('SHOW WARNINGS')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([[null, null]], $result->rows);
        self::assertInstanceOf(ResultSet::class, $warnings);
        self::assertSame([['Warning', '1292'], ['Warning', '1292']], [[$warnings->rows[0][0], $warnings->rows[0][1]], [$warnings->rows[1][0], $warnings->rows[1][1]]]);
    }

    public function testTimeTakesTheTimeOfADatetime(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (dt DATETIME)');
        $session->query("INSERT INTO t VALUES ('2024-01-31 12:00:05')");
        $result = $session->query("SELECT CAST(dt AS TIME), CAST(TIMESTAMP '2024-01-15 10:20:30' AS TIME) FROM t")[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['12:00:05', '10:20:30']], $result->rows);
    }

    public function testTimeReadsStringsAndNumberDigits(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query("SELECT CAST('10:20:30' AS TIME), CAST(102030 AS TIME), CAST('-01:02:03' AS TIME)")[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['10:20:30', '10:20:30', '-01:02:03']], $result->rows);
        self::assertSame(0, $result->warnings);
    }

    public function testTimeWarnsForMinutesOutOfRange(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query("SELECT CAST('10:61:00' AS TIME)")[0];
        $warnings = $session->query('SHOW WARNINGS')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([[null]], $result->rows);
        self::assertInstanceOf(ResultSet::class, $warnings);
        self::assertSame([['Warning', '1292', "Truncated incorrect time value: '10:61:00'"]], $warnings->rows);
    }

    public function testYearMapsTwoDigitNumbersToTheirCentury(): void
    {
        $instance = new Instance();
        $context = new Context(new SqlModes([]), new Diagnostics(), new Variables($instance->catalog, $instance->globals), 0.0);
        $moments = new Moments();

        self::assertSame([2024, 2069, 1970, 1999], [$moments->year(24, Domain::integer(), $context), $moments->year(69, Domain::integer(), $context), $moments->year(70, Domain::integer(), $context), $moments->year(99, Domain::integer(), $context)]);
    }

    public function testYearKeepsZeroAndFourDigitYearsInRange(): void
    {
        $instance = new Instance();
        $context = new Context(new SqlModes([]), new Diagnostics(), new Variables($instance->catalog, $instance->globals), 0.0);
        $moments = new Moments();

        self::assertSame([0, 1901, 2155], [$moments->year(0, Domain::integer(), $context), $moments->year(1901, Domain::integer(), $context), $moments->year(2155, Domain::integer(), $context)]);
    }

    public function testYearConvertsThroughCast(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query("SELECT CAST(24 AS YEAR), CAST(75 AS YEAR), CAST('1999' AS YEAR), CAST(2155 AS YEAR), CAST(0 AS YEAR)")[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['2024', '1975', '1999', '2155', '0']], $result->rows);
    }

    public function testDigitsWritesANumberAsDecimalText(): void
    {
        $instance = new Instance();
        $context = new Context(new SqlModes([]), new Diagnostics(), new Variables($instance->catalog, $instance->globals), 0.0);
        $moments = new Moments();

        self::assertSame(['20240115', '1.5'], [$moments->digits(20240115, Domain::integer(), $context), $moments->digits(1.5, Domain::double(), $context)]);
    }

    public function testConvertRoundsFractionalSecondsHalfUp(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query("SELECT CAST('2024-01-15 10:20:30.123789' AS DATETIME(3)), CAST('2024-12-31 23:59:59.5' AS DATETIME), CAST('2024-12-31 23:59:59.9999995' AS DATETIME(6)), CAST('2024-12-31 23:59:59.5' AS DATE), CAST('2024-01-01 10:00:00.04999995' AS DATETIME(1))")[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['2024-01-15 10:20:30.124', '2025-01-01 00:00:00', '2025-01-01 00:00:00.000000', '2024-12-31', '2024-01-01 10:00:00.1']], $result->rows);
    }

    public function testConvertTruncatesFractionalSecondsUnderTimeTruncateFractional(): void
    {
        $session = (new Instance())->connect();
        $session->query("SET sql_mode = CONCAT(@@sql_mode, ',TIME_TRUNCATE_FRACTIONAL')");
        $result = $session->query("SELECT CAST('2024-01-15 10:20:30.123789' AS DATETIME(3)), CAST('10:20:30.987' AS TIME(1)), CAST('2024-12-31 23:59:59.9999995' AS DATETIME(6))")[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['2024-01-15 10:20:30.123', '10:20:30.9', '2024-12-31 23:59:59.999999']], $result->rows);
    }

    public function testConvertWarnsForAnOverflowPastTheLastDatetime(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query("SELECT CAST('9999-12-31 23:59:59.5' AS DATETIME)")[0];
        $warnings = $session->query('SHOW WARNINGS')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([[null]], $result->rows);
        self::assertInstanceOf(ResultSet::class, $warnings);
        self::assertSame([['Warning', '1441', 'Datetime function: datetime field overflow']], $warnings->rows);
    }

    public function testConvertKeepsTheValueBeforeTrailingText(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query("SELECT CAST('2024-01-01x' AS DATE), CAST('2024-1-1 1:2:3.5x' AS DATETIME(1)), CAST('xx' AS DATE)")[0];
        $warnings = $session->query('SHOW WARNINGS')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['2024-01-01', '2024-01-01 01:02:03.5', null]], $result->rows);
        self::assertInstanceOf(ResultSet::class, $warnings);
        self::assertSame([['Warning', '1292', "Truncated incorrect date value: '2024-01-01x'"], ['Warning', '1292', "Truncated incorrect datetime value: '2024-1-1 1:2:3.5x'"], ['Warning', '1292', "Incorrect datetime value: 'xx'"]], $warnings->rows);
    }

    public function testTimeRoundsFractionalSecondsWithTheCarry(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query("SELECT CAST('10:20:30.987' AS TIME(1)), CAST('23:59:59.9' AS TIME), CAST('-10:20:30.5' AS TIME), CAST(TIME '10:00:00.56' AS TIME(1)), CAST('-0:00:00' AS TIME)")[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['10:20:31.0', '24:00:00', '-10:20:31', '10:00:00.6', '-00:00:00']], $result->rows);
    }

    public function testTimeTakesTheTimeOfADatetimeString(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query("SELECT CAST('2024-02-29 10:11:12' AS TIME), CAST('24-02-29 10:11' AS TIME), CAST('20240229101112' AS TIME), CAST(20240229101112.5 AS TIME(1)), CAST('2024-02-30 10:11:12' AS TIME), CAST('2024-02-29' AS TIME)")[0];
        $warnings = $session->query('SHOW WARNINGS')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['10:11:12', '10:11:00', '10:11:12', '10:11:12.5', null, '00:20:24']], $result->rows);
        self::assertInstanceOf(ResultSet::class, $warnings);
        self::assertSame([['Warning', '1292', "Truncated incorrect time value: '2024-02-30 10:11:12'"], ['Warning', '1292', "Truncated incorrect time value: '2024-02-29'"]], $warnings->rows);
    }

    public function testTimeClampsAStringBeyondTheRangeWithAWarning(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query("SELECT CAST('900:00:00' AS TIME), CAST('838:59:59.5' AS TIME), CAST('838:59:58.5' AS TIME), CAST(8390000 AS TIME), CAST(8385959.5 AS TIME)")[0];
        $warnings = $session->query('SHOW WARNINGS')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['838:59:59', '838:59:59', '838:59:59', null, '838:59:59']], $result->rows);
        self::assertInstanceOf(ResultSet::class, $warnings);
        self::assertSame([['Warning', '1292', "Truncated incorrect time value: '900:00:00'"], ['Warning', '1292', "Truncated incorrect time value: '838:59:59.5'"], ['Warning', '1292', "Truncated incorrect time value: '8390000'"]], $warnings->rows);
    }

    public function testYearReadsOneAndTwoDigitStringsAsYearsOfTheCentury(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query("SELECT CAST('24' AS YEAR), CAST('0' AS YEAR), CAST('0000' AS YEAR), CAST('5' AS YEAR), CAST('70' AS YEAR), CAST(' +024' AS YEAR), CAST(0.5 AS YEAR)")[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['2024', '2000', '2000', '2005', '1970', '2024', '2001']], $result->rows);
    }

    public function testYearWarnsForAStringWithoutAYearOrOutsideTheRange(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query("SELECT CAST('x' AS YEAR), CAST('-1' AS YEAR), CAST('24x' AS YEAR), CAST('100x' AS YEAR), CAST(2156.5 AS YEAR)")[0];
        $warnings = $session->query('SHOW WARNINGS')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([[null, null, '2024', null, null]], $result->rows);
        self::assertInstanceOf(ResultSet::class, $warnings);
        self::assertSame([['Warning', '1525', "Incorrect YEAR value: 'x'"], ['Warning', '1525', "Incorrect YEAR value: '-1'"], ['Warning', '1292', "Truncated incorrect YEAR value: '24x'"], ['Warning', '1292', "Truncated incorrect YEAR value: '100x'"], ['Warning', '1292', "Truncated incorrect YEAR value: '100'"], ['Warning', '1292', "Truncated incorrect YEAR value: '2157'"]], $warnings->rows);
    }

    public function testYearTakesTheYearOfADate(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query("SELECT CAST(DATE '2024-03-01' AS YEAR), CAST(TIMESTAMP '1999-03-01 10:00:00' AS YEAR)")[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['2024', '1999']], $result->rows);
    }
}
