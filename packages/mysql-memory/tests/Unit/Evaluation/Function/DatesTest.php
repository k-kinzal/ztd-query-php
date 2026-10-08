<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Function;

use MySqlMemory\Evaluation\Context;
use MySqlMemory\Evaluation\Frame;
use MySqlMemory\Evaluation\Function\Dates;
use MySqlMemory\Evaluation\Leaf\Constant;
use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use MySqlMemory\Session\Diagnostics;
use MySqlMemory\Session\SqlModes;
use MySqlMemory\Session\Variables;
use MySqlMemory\Typing\Domain;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Expression\IntervalUnit;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind;

#[CoversClass(Dates::class)]
#[Small]
final class DatesTest extends TestCase
{
    public function testRoutinesNamesTheDateAndTimePartFunctions(): void
    {
        $names = array_map(static fn ($routine): string => $routine->name, (new Dates())->routines());

        self::assertSame(['DATE', 'YEAR', 'MONTH', 'DAY', 'DAYOFMONTH', 'QUARTER', 'DAYOFYEAR', 'DAYOFWEEK', 'WEEKDAY', 'HOUR', 'MINUTE', 'SECOND', 'MICROSECOND'], $names);
    }

    public function testMomentKeepsTheDateOfADatetime(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query("SELECT DATE('2003-12-31 01:02:03'), DATE(NULL)")[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['2003-12-31', null]], $result->rows);
        self::assertSame(Field::Date, $result->columns[0]->type);
    }

    public function testMomentReadsANumberAsADate(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query('SELECT DATE(20240229)')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['2024-02-29']], $result->rows);
    }

    public function testMomentAnswersNullWithAWarningForAStringThatIsNoDate(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query("SELECT DATE('x')")[0];
        $warnings = $session->query('SHOW WARNINGS')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertInstanceOf(ResultSet::class, $warnings);
        self::assertSame([[null]], $result->rows);
        self::assertSame([['Warning', '1292']], array_map(static fn (array $row): array => array_slice($row, 0, 2), $warnings->rows));
    }

    public function testMomentConvertsAStringArgumentDirectly(): void
    {
        $instance = new Instance();
        $frame = new Frame(new Context(new SqlModes([]), new Diagnostics(), new Variables($instance->catalog, $instance->globals), 0.0));
        $text = Domain::string(19, Collation::known('utf8mb4_0900_ai_ci'));

        self::assertSame('2001-02-03', (new Dates())->moment($frame, new Constant($text, '2001-02-03 04:05:06'), Kind::Date));
        self::assertNull((new Dates())->moment($frame, new Constant($text, null), Kind::Date));
    }

    public function testPartReadsTheDateParts(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query("SELECT YEAR('1987-01-01'), MONTH('2008-02-03'), DAY('2007-02-03'), DAYOFMONTH('2007-02-03'), QUARTER('2008-04-01'), DAYOFYEAR('2007-02-03'), DAYOFWEEK('2007-02-03'), WEEKDAY('2008-02-03 22:23:00'), WEEKDAY('2007-11-06')")[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['1987', '2', '3', '3', '2', '34', '7', '6', '1']], $result->rows);
    }

    public function testPartReadsTheTimeParts(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query("SELECT HOUR('10:05:03'), HOUR('272:59:59'), MINUTE('2008-02-03 10:05:03'), SECOND('10:05:03'), MICROSECOND('12:00:00.123456'), MICROSECOND('2019-12-31 23:59:59.000010')")[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['10', '272', '5', '3', '123456', '10']], $result->rows);
    }

    public function testPartReadsTypedTemporalValues(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query("SELECT YEAR(TIMESTAMP '2001-02-03 04:05:06'), DAYOFWEEK(DATE '2024-02-29'), HOUR(TIME '-01:02:03'), HOUR(DATE '2024-01-01')")[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['2001', '5', '1', '0']], $result->rows);
    }

    public function testPartAnswersNullForNull(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query('SELECT YEAR(NULL), HOUR(NULL)')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([[null, null]], $result->rows);
    }

    public function testPartAnswersNullWithAWarningForAnInvalidDate(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query("SELECT MONTH('2008-13-01'), HOUR('x')")[0];
        $warnings = $session->query('SHOW WARNINGS')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertInstanceOf(ResultSet::class, $warnings);
        self::assertSame([[null, null]], $result->rows);
        self::assertSame([['Warning', '1292'], ['Warning', '1292']], array_map(static fn (array $row): array => array_slice($row, 0, 2), $warnings->rows));
    }

    public function testReadNamesEachPartOfAMoment(): void
    {
        $dates = new Dates();
        $parts = [2024, 2, 29, 13, 14, 15, 16];
        $read = array_map(static fn (string $name): int => $dates->read($name, $parts), ['YEAR', 'MONTH', 'DAY', 'DAYOFMONTH', 'QUARTER', 'DAYOFYEAR', 'DAYOFWEEK', 'WEEKDAY', 'HOUR', 'MINUTE', 'SECOND', 'MICROSECOND']);

        self::assertSame([2024, 2, 29, 29, 1, 60, 5, 3, 13, 14, 15, 16], $read);
    }

    public function testReadCountsTheQuarterFromTheMonth(): void
    {
        $dates = new Dates();
        $quarters = array_map(static fn (int $month): int => $dates->read('QUARTER', [2024, $month, 1, 0, 0, 0, 0]), [1, 3, 4, 6, 7, 9, 10, 12]);

        self::assertSame([1, 1, 2, 2, 3, 3, 4, 4], $quarters);
    }

    public function testPartReadsATimeAsADatetimeOnTheCurrentDate(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query("SELECT DAY(TIME '25:00:00') = DAY(CURDATE() + INTERVAL 1 DAY), DATE(TIME '25:00:00') = CURDATE() + INTERVAL 1 DAY, MONTH(TIME '-25:00:00') = MONTH(CURDATE() - INTERVAL 2 DAY), YEAR(TIME '00:00:00') = YEAR(CURDATE())")[0];
        $warnings = $session->query('SHOW WARNINGS')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertInstanceOf(ResultSet::class, $warnings);
        self::assertSame([['1', '1', '1', '1']], $result->rows);
        self::assertSame([], $warnings->rows);
    }

    public function testExtractReadsAStringThatHoldsNoDatetimeAsATimeForAUnitOfTheTime(): void
    {
        $instance = new Instance();
        $frame = new Frame(new Context(new SqlModes([]), new Diagnostics(), new Variables($instance->catalog, $instance->globals), 0.0));
        $text = Domain::string(19, Collation::known('utf8mb4_0900_ai_ci'));

        self::assertSame(-10203, (new Dates())->extract($frame, new Constant($text, '-01:02:03'), IntervalUnit::HourSecond));
        self::assertSame(20102, (new Dates())->extract($frame, new Constant($text, '2019-07-02 01:02:03'), IntervalUnit::DayMinute));
        self::assertNull((new Dates())->extract($frame, new Constant($text, null), IntervalUnit::Year));
    }

    public function testUnitWritesThePartsOfEachUnitTogether(): void
    {
        $dates = new Dates();
        $parts = [2024, 2, 29, 13, 14, 15, 16];
        $units = array_map(static fn (IntervalUnit $unit): int => $dates->unit($unit, $parts), IntervalUnit::cases());

        self::assertSame([16, 15, 14, 13, 29, 9, 2, 1, 2024, 15000016, 1415000016, 1415, 131415000016, 131415, 1314, 29131415000016, 29131415, 291314, 2913, 202402], $units);
    }
}
