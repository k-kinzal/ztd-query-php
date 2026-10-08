<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Operator;

use MySqlMemory\Evaluation\Leaf\Constant;
use MySqlMemory\Evaluation\Operator\DateShift;
use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use MySqlMemory\Typing\Domain;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Expression\IntervalUnit;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field;

#[CoversClass(DateShift::class)]
#[Small]
final class DateShiftTest extends TestCase
{
    public function testEvaluateMovesADateStringByDaysWeeksAndQuarters(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query("SELECT DATE_ADD('2024-01-15', INTERVAL 1 DAY), DATE_ADD(20240115, INTERVAL 1 DAY), DATE_ADD('2024-01-15', INTERVAL -1 WEEK), DATE_ADD('2024-01-15', INTERVAL 1 QUARTER), DATE_SUB('2024-03-01', INTERVAL 1 DAY)")[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['2024-01-16', '2024-01-16', '2024-01-08', '2024-04-15', '2024-02-29']], $result->rows);
    }

    public function testEvaluateKeepsTheDayWithinTheMonthReached(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query("SELECT '2024-01-31' + INTERVAL 1 MONTH, DATE_SUB('2024-03-31', INTERVAL 1 MONTH), DATE_ADD(DATE '2024-02-29', INTERVAL 1 YEAR), DATE '2024-01-15' + INTERVAL '1-2' YEAR_MONTH")[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['2024-02-29', '2024-02-29', '2025-02-28', '2025-03-15']], $result->rows);
        self::assertSame(Field::Date, $result->columns[2]->type);
    }

    public function testEvaluateMovesTableColumns(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (s VARCHAR(20), dt DATETIME, tm TIME)');
        $session->query("INSERT INTO t VALUES ('2024-01-15', '2024-01-31 12:00:00', '12:30:00')");
        $result = $session->query('SELECT s + INTERVAL 1 DAY, dt + INTERVAL 1 MONTH, tm + INTERVAL 45 MINUTE FROM t')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['2024-01-16', '2024-02-29 12:00:00', '13:15:00']], $result->rows);
        self::assertSame([Field::DateTime, Field::Time], [$result->columns[1]->type, $result->columns[2]->type]);
    }

    public function testEvaluateReturnsNullForANullOperandOrQuantity(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query("SELECT DATE_ADD(NULL, INTERVAL 1 DAY), DATE_ADD('2024-01-15', INTERVAL NULL DAY)")[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([[null, null]], $result->rows);
        self::assertSame(0, $result->warnings);
    }

    public function testEvaluateWarnsForAValueThatIsNoDate(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query("SELECT DATE_ADD('2024-02-30', INTERVAL 1 DAY)")[0];
        $warnings = $session->query('SHOW WARNINGS')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([[null]], $result->rows);
        self::assertInstanceOf(ResultSet::class, $warnings);
        self::assertSame(['Warning', '1292'], [$warnings->rows[0][0], $warnings->rows[0][1]]);
    }

    public function testEvaluateWarnsForAResultPastTheLastYear(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query("SELECT DATE_ADD('9999-12-31', INTERVAL 1 DAY)")[0];
        $warnings = $session->query('SHOW WARNINGS')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([[null]], $result->rows);
        self::assertInstanceOf(ResultSet::class, $warnings);
        self::assertSame([['Warning', '1441', 'Datetime function: datetime field overflow']], $warnings->rows);
    }

    public function testEvaluateWarnsForAMonthShiftPastTheLastYear(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query("SELECT DATE_ADD('9999-12-15', INTERVAL 1 MONTH)")[0];
        $warnings = $session->query('SHOW WARNINGS')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([[null]], $result->rows);
        self::assertInstanceOf(ResultSet::class, $warnings);
        self::assertSame([['Warning', '1441', 'Datetime function: datetime field overflow']], $warnings->rows);
    }

    public function testWriteAddsATimeToADateStringForATimeUnit(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query("SELECT DATE_ADD('2024-01-15', INTERVAL 1 HOUR), DATE_SUB('2024-01-15 10:00:00', INTERVAL '1:30' HOUR_MINUTE)")[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['2024-01-15 01:00:00', '2024-01-15 08:30:00']], $result->rows);
    }

    public function testWriteShowsSixDecimalsForAFractionalStringResult(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query("SELECT DATE_ADD('2024-01-15', INTERVAL 1.5 SECOND), DATE_ADD('2024-01-15 10:00:00.5', INTERVAL 1 SECOND)")[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['2024-01-15 00:00:01.500000', '2024-01-15 10:00:01.500000']], $result->rows);
    }

    public function testWriteUsesTheDecimalsOfADatetimeResult(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query("SELECT TIMESTAMP '2024-01-15 10:00:00.5' + INTERVAL 1 SECOND, DATE_ADD(TIMESTAMP '2024-01-15 10:00:00', INTERVAL 1 YEAR)")[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['2024-01-15 10:00:01.5', '2025-01-15 10:00:00']], $result->rows);
        self::assertSame([Field::DateTime, 1], [$result->columns[0]->type, $result->columns[0]->decimals]);
    }

    public function testTimeMovesATimeAcrossZeroAndPastADay(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query("SELECT TIME '10:00:00' - INTERVAL 11 HOUR, DATE_ADD(TIME '23:00:00', INTERVAL 2 HOUR), TIME '10:00:00' + INTERVAL 30 MINUTE")[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['-01:00:00', '25:00:00', '10:30:00']], $result->rows);
        self::assertSame(Field::Time, $result->columns[0]->type);
    }

    public function testDomainAnswersTheDomainOfTheResult(): void
    {
        $domain = Domain::string(29, Collation::binary());
        $shift = new DateShift(new Constant(Domain::string(10, Collation::binary()), '2024-01-15'), new Constant(Domain::integer(), 1), IntervalUnit::Second, false, $domain);

        self::assertSame($domain, $shift->domain());
    }

    public function testEvaluateLeavesTheIntervalUnreadForAValueThatIsNoDate(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query("SELECT 'x' + INTERVAL (1 DIV 0) DAY, NULL + INTERVAL (1 DIV 0) DAY")[0];
        $warnings = $session->query('SHOW WARNINGS')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([[null, null]], $result->rows);
        self::assertInstanceOf(ResultSet::class, $warnings);
        self::assertSame([['Warning', '1292', "Incorrect datetime value: 'x'"]], $warnings->rows);
    }

    public function testMomentQuotesANumberAsTheServerWritesIt(): void
    {
        $session = (new Instance())->connect();
        $session->query('SELECT ~0 + INTERVAL 1 DAY, -0e0 + INTERVAL 1 DAY, 1e20 + INTERVAL 1 DAY, 0x0E + INTERVAL 1 DAY');
        $warnings = $session->query('SHOW WARNINGS')[0];

        self::assertInstanceOf(ResultSet::class, $warnings);
        self::assertSame([
            ['Warning', '1292', "Incorrect datetime value: '-1'"],
            ['Warning', '1292', "Incorrect datetime value: '-0'"],
            ['Warning', '1292', "Incorrect datetime value: '1e20'"],
            ['Warning', '1292', "Incorrect datetime value: '\\x0E'"],
        ], $warnings->rows);
    }

    public function testMomentReadsADateFollowedByMoreTextWithAWarning(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query("SELECT '2020-01-01x' + INTERVAL 1 DAY")[0];
        $warnings = $session->query('SHOW WARNINGS')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['2020-01-02']], $result->rows);
        self::assertInstanceOf(ResultSet::class, $warnings);
        self::assertSame([['Warning', '1292', "Truncated incorrect date value: '2020-01-01x'"]], $warnings->rows);
    }

    public function testIntervalReadsTheQuantityAsTheUnitTakesIt(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query("SELECT '2020-01-01' + INTERVAL '1.5' DAY, '2020-01-01' + INTERVAL '1x' SECOND, '2020-01-01' + INTERVAL 'a' DAY_HOUR, '2020-01-01' + INTERVAL '1 2 3' DAY_HOUR")[0];
        $warnings = $session->query('SHOW WARNINGS')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['2020-01-02', '2020-01-01 00:00:01', '2020-01-01 00:00:00', null]], $result->rows);
        self::assertInstanceOf(ResultSet::class, $warnings);
        self::assertSame([
            ['Warning', '1292', "Truncated incorrect INTEGER value: '1.5'"],
            ['Warning', '1292', "Truncated incorrect DECIMAL value: '1x'"],
            ['Warning', '1441', 'Datetime function: date_add_interval field overflow'],
        ], $warnings->rows);
    }

}
