<?php

declare(strict_types=1);

namespace Tests\Unit\Storage;

use MySqlMemory\Dictionary\ColumnDefinition;
use MySqlMemory\Dictionary\Fill;
use MySqlMemory\Error\DataError;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Evaluation\Context;
use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use MySqlMemory\Session\Diagnostics;
use MySqlMemory\Session\Globals;
use MySqlMemory\Session\SqlModes;
use MySqlMemory\Session\Variables;
use MySqlMemory\Storage\Store;
use MySqlMemory\Storage\Times;
use MySqlMemory\Typing\Domain;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind;
use SqlSemantics\Platform\MySql\Statement\Variable\Catalog\SystemVariables;

#[CoversClass(Times::class)]
#[Small]
final class TimesTest extends TestCase
{
    public function testValueWritesADateFromAString(): void
    {
        $context = new Context(new SqlModes([]), new Diagnostics(), new Variables(SystemVariables::of(GrammarRelease::MySql847), new Globals()), 0.0);
        $column = new ColumnDefinition('c', new Domain(Kind::Date, Field::Date, 10), Fill::none());

        self::assertSame(['2024-03-01', '1999-01-02'], [(new Times(new Store($context)))->value('2024-3-1', Domain::string(8, Collation::known('utf8mb4_0900_ai_ci')), $column), (new Times(new Store($context)))->value('99-01-02', Domain::string(8, Collation::known('utf8mb4_0900_ai_ci')), $column)]);
    }

    public function testValueReadsANumberAsADate(): void
    {
        $context = new Context(new SqlModes([]), new Diagnostics(), new Variables(SystemVariables::of(GrammarRelease::MySql847), new Globals()), 0.0);
        $column = new ColumnDefinition('c', new Domain(Kind::Date, Field::Date, 10), Fill::none());

        self::assertSame('2024-03-01', (new Times(new Store($context)))->value(20240301, Domain::integer(), $column));
    }

    public function testValueDropsTheTimeOfADatetimeStoredIntoADate(): void
    {
        $context = new Context(new SqlModes([]), new Diagnostics(), new Variables(SystemVariables::of(GrammarRelease::MySql847), new Globals()), 0.0);
        $column = new ColumnDefinition('c', new Domain(Kind::Date, Field::Date, 10), Fill::none());

        self::assertSame('2024-03-01', (new Times(new Store($context)))->value('2024-03-01 12:00:00', Domain::string(19, Collation::known('utf8mb4_0900_ai_ci')), $column));
    }

    public function testValueRoundsFractionalSecondsIntoTheNextDay(): void
    {
        $context = new Context(new SqlModes([]), new Diagnostics(), new Variables(SystemVariables::of(GrammarRelease::MySql847), new Globals()), 0.0);
        $column = new ColumnDefinition('c', new Domain(Kind::DateTime, Field::DateTime, 22, 2), Fill::none());

        self::assertSame('2024-03-02 00:00:00.00', (new Times(new Store($context)))->value('2024-03-01 23:59:59.996', Domain::string(23, Collation::known('utf8mb4_0900_ai_ci')), $column));
    }

    public function testValueStoresTheZeroDateForAnInvalidDate(): void
    {
        $context = new Context(new SqlModes([]), new Diagnostics(), new Variables(SystemVariables::of(GrammarRelease::MySql847), new Globals()), 0.0);
        $column = new ColumnDefinition('c', new Domain(Kind::Date, Field::Date, 10), Fill::none());

        $value = (new Times(new Store($context, 3)))->value('2024-02-30', Domain::string(10, Collation::known('utf8mb4_0900_ai_ci')), $column);

        self::assertSame(['0000-00-00', 'Warning', 1264, "Out of range value for column 'c' at row 3"], [$value, $context->diagnostics->conditions[0][0], $context->diagnostics->conditions[0][1], $context->diagnostics->conditions[0][2]]);
    }

    public function testValueWritesATimeFromAString(): void
    {
        $context = new Context(new SqlModes([]), new Diagnostics(), new Variables(SystemVariables::of(GrammarRelease::MySql847), new Globals()), 0.0);
        $column = new ColumnDefinition('c', new Domain(Kind::Time, Field::Time, 10), Fill::none());

        self::assertSame('26:03:04', (new Times(new Store($context)))->value('1 02:03:04', Domain::string(10, Collation::known('utf8mb4_0900_ai_ci')), $column));
    }

    public function testTimeTakesTheTimeOfADatetime(): void
    {
        $context = new Context(new SqlModes([]), new Diagnostics(), new Variables(SystemVariables::of(GrammarRelease::MySql847), new Globals()), 0.0);
        $column = new ColumnDefinition('c', new Domain(Kind::Time, Field::Time, 12, 1), Fill::none());

        self::assertSame('12:34:56.5', (new Times(new Store($context)))->time('2024-03-01 12:34:56.5', new Domain(Kind::DateTime, Field::DateTime, 21, 1), $column));
    }

    public function testTimeReadsHoursMinutesAndSeconds(): void
    {
        $context = new Context(new SqlModes([]), new Diagnostics(), new Variables(SystemVariables::of(GrammarRelease::MySql847), new Globals()), 0.0);
        $column = new ColumnDefinition('c', new Domain(Kind::Time, Field::Time, 13, 2), Fill::none());
        $from = Domain::string(10, Collation::known('utf8mb4_0900_ai_ci'));

        self::assertSame(['-12:34:00.00', '12:34:56.70'], [(new Times(new Store($context)))->time('-12:34', $from, $column), (new Times(new Store($context)))->time('123456.7', $from, $column)]);
    }

    public function testTimeClipsHoursAboveTheRange(): void
    {
        $context = new Context(new SqlModes([]), new Diagnostics(), new Variables(SystemVariables::of(GrammarRelease::MySql847), new Globals()), 0.0);
        $column = new ColumnDefinition('c', new Domain(Kind::Time, Field::Time, 10), Fill::none());

        $value = (new Times(new Store($context)))->time('900:00:00', Domain::string(9, Collation::known('utf8mb4_0900_ai_ci')), $column);

        self::assertSame(['838:59:59', 1], [$value, count($context->diagnostics->conditions)]);
    }

    public function testProblemStoresTheZeroValueOfTheColumn(): void
    {
        $context = new Context(new SqlModes([]), new Diagnostics(), new Variables(SystemVariables::of(GrammarRelease::MySql847), new Globals()), 0.0);
        $store = new Store($context);

        self::assertSame(['0000-00-00', '00:00:00.0', '0000-00-00 00:00:00.000'], [(new Times($store))->problem(DataError::DataTruncated, 'x', new ColumnDefinition('c', new Domain(Kind::Date, Field::Date, 10), Fill::none())), (new Times($store))->problem(DataError::DataTruncated, 'x', new ColumnDefinition('c', new Domain(Kind::Time, Field::Time, 12, 1), Fill::none())), (new Times($store))->problem(DataError::OutOfRange, 'x', new ColumnDefinition('c', new Domain(Kind::DateTime, Field::DateTime, 23, 3), Fill::none()))]);
    }

    public function testProblemWarnsOfTheConditionWithoutAStrictMode(): void
    {
        $context = new Context(new SqlModes([]), new Diagnostics(), new Variables(SystemVariables::of(GrammarRelease::MySql847), new Globals()), 0.0);

        (new Times(new Store($context, 2)))->problem(DataError::DataTruncated, 'nope', new ColumnDefinition('c', new Domain(Kind::DateTime, Field::Timestamp, 19), Fill::none()));

        self::assertSame([['Warning', 1265, "Data truncated for column 'c' at row 2"]], $context->diagnostics->conditions);
    }

    public function testProblemRaisesAnErrorNamingTheValueUnderAStrictMode(): void
    {
        $context = new Context(new SqlModes([]), new Diagnostics(), new Variables(SystemVariables::of(GrammarRelease::MySql847), new Globals()), 0.0, true);

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1292);
        $this->expectExceptionMessage("Incorrect time value: '12:61:00' for column 'c' at row 1");

        (new Times(new Store($context)))->problem(DataError::OutOfRange, '12:61:00', new ColumnDefinition('c', new Domain(Kind::Time, Field::Time, 10), Fill::none()));
    }

    public function testDroppedNotesTheValueUnderAStrictModeAndTheTruncationOtherwise(): void
    {
        $strict = new Context(new SqlModes([]), new Diagnostics(), new Variables(SystemVariables::of(GrammarRelease::MySql847), new Globals()), 0.0, true);
        $loose = new Context(new SqlModes([]), new Diagnostics(), new Variables(SystemVariables::of(GrammarRelease::MySql847), new Globals()), 0.0);
        $column = new ColumnDefinition('d', new Domain(Kind::Date, Field::Date, 10), Fill::none());

        (new Times(new Store($strict)))->dropped('2024-12-31 10:00:00', $column);
        (new Times(new Store($loose)))->dropped('2024-12-31 10:00:00', $column);

        self::assertSame([['Note', 1292, "Incorrect date value: '2024-12-31 10:00:00' for column 'd' at row 1"], ['Note', 1265, "Data truncated for column 'd' at row 1"]], [$strict->diagnostics->conditions[0], $loose->diagnostics->conditions[0]]);
    }

    public function testKindNamesTheTypeOfTheColumn(): void
    {
        $times = new Times(new Store(new Context(new SqlModes([]), new Diagnostics(), new Variables(SystemVariables::of(GrammarRelease::MySql847), new Globals()), 0.0)));

        self::assertSame(['date', 'time', 'datetime', 'datetime'], [$times->kind(new ColumnDefinition('c', new Domain(Kind::Date, Field::Date, 10), Fill::none())), $times->kind(new ColumnDefinition('c', new Domain(Kind::Time, Field::Time, 10), Fill::none())), $times->kind(new ColumnDefinition('c', new Domain(Kind::DateTime, Field::DateTime, 19), Fill::none())), $times->kind(new ColumnDefinition('c', new Domain(Kind::DateTime, Field::Timestamp, 19), Fill::none()))]);
    }

    public function testValueRefusesAnInvalidDateWithTheValueAndTheColumnUnderAStrictMode(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (d DATE)');

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1292);
        $this->expectExceptionMessage("Incorrect date value: '2024-02-30' for column 'd' at row 1");

        $session->query("INSERT INTO t (d) VALUES ('2024-02-30')");
    }

    public function testValueWarnsOfOutOfRangeAndTruncatedValuesUnderInsertIgnore(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (d DATE, e TIME)');
        $session->query("INSERT IGNORE INTO t VALUES ('2024-02-30', '900:00:00'), ('2024-01-01x', 'xx')");
        $warnings = $session->query('SHOW WARNINGS')[0];
        $rows = $session->query('SELECT * FROM t')[0];

        self::assertInstanceOf(ResultSet::class, $warnings);
        self::assertInstanceOf(ResultSet::class, $rows);
        self::assertSame([['Warning', '1264', "Out of range value for column 'd' at row 1"], ['Warning', '1264', "Out of range value for column 'e' at row 1"], ['Warning', '1265', "Data truncated for column 'd' at row 2"], ['Warning', '1265', "Data truncated for column 'e' at row 2"]], $warnings->rows);
        self::assertSame([['0000-00-00', '838:59:59'], ['2024-01-01', '00:00:00']], $rows->rows);
    }

    public function testValueRefusesATimeOutOfRangeUnderAStrictMode(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (e TIME)');

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1292);
        $this->expectExceptionMessage("Incorrect time value: '900:00:00' for column 'e' at row 1");

        $session->query("INSERT INTO t (e) VALUES ('900:00:00')");
    }

    public function testValueStoresZeroDatesOnlyWhereTheModeAllowsThem(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (d DATETIME)');
        $session->query("SET sql_mode = ''");
        $session->query("INSERT INTO t VALUES ('2024-00-01'), ('0000-00-00')");
        $session->query("SET sql_mode = 'NO_ZERO_DATE,NO_ZERO_IN_DATE'");
        $session->query("INSERT INTO t VALUES ('2024-00-01'), (0)");
        $warnings = $session->query('SHOW WARNINGS')[0];
        $rows = $session->query('SELECT * FROM t')[0];

        self::assertInstanceOf(ResultSet::class, $warnings);
        self::assertInstanceOf(ResultSet::class, $rows);
        self::assertSame([['Warning', '1264', "Out of range value for column 'd' at row 1"], ['Warning', '1264', "Out of range value for column 'd' at row 2"]], $warnings->rows);
        self::assertSame([['2024-00-01 00:00:00'], ['0000-00-00 00:00:00'], ['0000-00-00 00:00:00'], ['0000-00-00 00:00:00']], $rows->rows);
    }

    public function testMomentRoundsFractionalSecondsWithTheCarryIntoTheDate(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (a DATETIME(1), b TIME(1), c TIMESTAMP(1) NULL, d DATETIME, f DATE)');
        $session->query("INSERT INTO t VALUES ('2024-12-31 23:59:59.96', '-10:59:59.96', '2024-12-31 23:59:59.96', '2024-01-01 10:00:00.99999995', '2024-12-31 23:59:59.5')");
        $session->query("SET sql_mode = CONCAT(@@sql_mode, ',TIME_TRUNCATE_FRACTIONAL')");
        $session->query("INSERT INTO t VALUES ('2024-12-31 23:59:59.96', '-10:59:59.96', '2024-12-31 23:59:59.96', '2024-01-01 10:00:00.99999995', '2024-12-31 23:59:59.5')");
        $result = $session->query('SELECT * FROM t')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['2025-01-01 00:00:00.0', '-11:00:00.0', '2025-01-01 00:00:00.0', '2024-01-01 10:00:01', '2025-01-01'], ['2024-12-31 23:59:59.9', '-10:59:59.9', '2024-12-31 23:59:59.9', '2024-01-01 10:00:00', '2024-12-31']], $result->rows);
    }

    public function testMomentRefusesATimestampOutsideItsRange(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (s TIMESTAMP NULL)');

        $this->expectException(SqlError::class);
        $this->expectExceptionMessage("Incorrect datetime value: '2038-01-19 03:14:07.5' for column 's' at row 1");

        $session->query("INSERT INTO t VALUES ('2038-01-19 03:14:07.5')");
    }

    public function testMomentReportsAnOverflowOfTheLastDatetime(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (d DATETIME)');
        $session->query("INSERT IGNORE INTO t VALUES ('9999-12-31 23:59:59.5')");
        $warnings = $session->query('SHOW WARNINGS')[0];

        self::assertInstanceOf(ResultSet::class, $warnings);
        self::assertSame([['Warning', '1441', 'Datetime function: datetime field overflow'], ['Warning', '1264', "Out of range value for column 'd' at row 1"]], $warnings->rows);
    }

    public function testScanReadsTheDateAndTheMicrosecondsOfAText(): void
    {
        $context = new Context(new SqlModes([]), new Diagnostics(), new Variables(SystemVariables::of(GrammarRelease::MySql847), new Globals()), 0.0);
        $times = new Times(new Store($context));

        self::assertSame([[2024, 3, 1, 10, 11, 12, '5', true, 'x'], 500000], $times->scan('2024-03-01 10:11:12.5x', Domain::string(22, Collation::known('utf8mb4_0900_ai_ci')), false));
        self::assertSame([[0, 0, 0, 0, 0, 0, '', false, ''], 0], $times->scan('0.0', Domain::decimal(2, 1), true));
        self::assertSame([null, 0], $times->scan('none', Domain::string(4, Collation::known('utf8mb4_0900_ai_ci')), false));
    }

    public function testOverflowsHoldsForAFieldBeyondItsRange(): void
    {
        self::assertSame([false, true, true, true], [Times::overflows([2024, 12, 31, 23, 59, 59, '', true, '']), Times::overflows([2024, 13, 1, 0, 0, 0, '', false, '']), Times::overflows([2024, 1, 32, 0, 0, 0, '', false, '']), Times::overflows([2024, 1, 1, 0, 0, 60, '', true, ''])]);
    }

    public function testLeftoverReportsTheTextAfterTheValueBeforeADroppedTime(): void
    {
        $context = new Context(new SqlModes([]), new Diagnostics(), new Variables(SystemVariables::of(GrammarRelease::MySql847), new Globals()), 0.0);
        $column = new ColumnDefinition('d', new Domain(Kind::Date, Field::Date, 10), Fill::none());
        $times = new Times(new Store($context));

        $times->leftover('2024-01-01 10:00:00x', 'x', [2024, 1, 1, 10, 0, 0, 0], $column);
        $times->leftover('2024-01-01 10:00:00', '', [2024, 1, 1, 10, 0, 0, 0], $column);
        $times->leftover('2024-01-01', '', [2024, 1, 1, 0, 0, 0, 0], $column);

        self::assertSame([['Warning', 1265, "Data truncated for column 'd' at row 1"], ['Note', 1265, "Data truncated for column 'd' at row 1"]], $context->diagnostics->conditions);
    }

    public function testFitClampsATimeBeyondTheRangeAfterReportingTheTextAfterIt(): void
    {
        $context = new Context(new SqlModes([]), new Diagnostics(), new Variables(SystemVariables::of(GrammarRelease::MySql847), new Globals()), 0.0);
        $column = new ColumnDefinition('e', new Domain(Kind::Time, Field::Time, 12, 1), Fill::none());
        $times = new Times(new Store($context));

        self::assertSame(['838:59:59.0', '-00:00:01.0', '00:00:00.0'], [$times->fit('900:00:00x', [false, 900, 0, 0, '', 'x'], false, $column), $times->fit('-00:00:00.96', [true, 0, 0, 0, '96', ''], false, $column), $times->fit('-00:00:00.01', [true, 0, 0, 0, '01', ''], false, $column)]);
        self::assertSame([[1265, "Data truncated for column 'e' at row 1"], [1264, "Out of range value for column 'e' at row 1"]], array_map(static fn (array $condition): array => [$condition[1], $condition[2]], $context->diagnostics->conditions));
    }

    public function testTimeTakesTheTimeOfADatetimeStringWithANote(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (e TIME)');
        $session->query("INSERT INTO t VALUES ('2024-02-29 10:11:12.5')");
        $warnings = $session->query('SHOW WARNINGS')[0];
        $rows = $session->query('SELECT * FROM t')[0];

        self::assertInstanceOf(ResultSet::class, $warnings);
        self::assertInstanceOf(ResultSet::class, $rows);
        self::assertSame([['Note', '1292', "Incorrect time value: '2024-02-29 10:11:12.5' for column 'e' at row 1"]], $warnings->rows);
        self::assertSame([['10:11:13']], $rows->rows);
    }

    public function testYearReadsTwoDigitYears(): void
    {
        $context = new Context(new SqlModes([]), new Diagnostics(), new Variables(SystemVariables::of(GrammarRelease::MySql847), new Globals()), 0.0);
        $column = new ColumnDefinition('c', new Domain(Kind::Year, Field::Year, 4, 0, true), Fill::none());
        $times = new Times(new Store($context));

        self::assertSame([2005, 2069, 1970, 1999], [$times->year(5, Domain::integer(), $column), $times->year(69, Domain::integer(), $column), $times->year(70, Domain::integer(), $column), $times->year(99, Domain::integer(), $column)]);
    }

    public function testYearReadsZeroAsTheZeroYearAndTheStringZeroZeroAs2000(): void
    {
        $context = new Context(new SqlModes([]), new Diagnostics(), new Variables(SystemVariables::of(GrammarRelease::MySql847), new Globals()), 0.0);
        $column = new ColumnDefinition('c', new Domain(Kind::Year, Field::Year, 4, 0, true), Fill::none());
        $times = new Times(new Store($context));

        self::assertSame([0, 2000, 2024], [$times->year(0, Domain::integer(), $column), $times->year('00', Domain::string(2, Collation::known('utf8mb4_0900_ai_ci')), $column), $times->year('2024', Domain::string(4, Collation::known('utf8mb4_0900_ai_ci')), $column)]);
    }

    public function testYearStoresZeroForAYearOutsideTheRange(): void
    {
        $context = new Context(new SqlModes([]), new Diagnostics(), new Variables(SystemVariables::of(GrammarRelease::MySql847), new Globals()), 0.0);
        $column = new ColumnDefinition('c', new Domain(Kind::Year, Field::Year, 4, 0, true), Fill::none());
        $times = new Times(new Store($context, 3));

        self::assertSame([0, 0, [['Warning', 1264, "Out of range value for column 'c' at row 3"], ['Warning', 1264, "Out of range value for column 'c' at row 3"]]], [$times->year(1900, Domain::integer(), $column), $times->year(2156, Domain::integer(), $column), $context->diagnostics->conditions]);
    }

    public function testYearReadsTheStringZeroAs2000UnlessItHasFourCharacters(): void
    {
        $context = new Context(new SqlModes([]), new Diagnostics(), new Variables(SystemVariables::of(GrammarRelease::MySql847), new Globals()), 0.0);
        $column = new ColumnDefinition('c', new Domain(Kind::Year, Field::Year, 4, 0, true), Fill::none());
        $times = new Times(new Store($context));
        $text = Domain::string(8, Collation::known('utf8mb4_0900_ai_ci'));

        self::assertSame([2000, 2000, 2000, 0, 0, 2000, 2025, 2001, 2024], [$times->year('0', $text, $column), $times->year('000', $text, $column), $times->year('0.0', $text, $column), $times->year('0000', $text, $column), $times->year('00.0', $text, $column), $times->year('0000 ', $text, $column), $times->year('2024.5', $text, $column), $times->year(0.5, Domain::decimal(2, 1), $column), $times->year(' 024', $text, $column)]);
    }

    public function testYearReportsAStringThatIsNoNumber(): void
    {
        $context = new Context(new SqlModes([]), new Diagnostics(), new Variables(SystemVariables::of(GrammarRelease::MySql847), new Globals()), 0.0);
        $column = new ColumnDefinition('y', new Domain(Kind::Year, Field::Year, 4, 0, true), Fill::none());
        $times = new Times(new Store($context));
        $text = Domain::string(8, Collation::known('utf8mb4_0900_ai_ci'));

        self::assertSame([0, 2024, [['Warning', 1366, "Incorrect integer value: 'x' for column 'y' at row 1"], ['Warning', 1265, "Data truncated for column 'y' at row 1"]]], [$times->year('x', $text, $column), $times->year('24x', $text, $column), $context->diagnostics->conditions]);
    }

    public function testYearTakesTheYearOfADate(): void
    {
        $context = new Context(new SqlModes([]), new Diagnostics(), new Variables(SystemVariables::of(GrammarRelease::MySql847), new Globals()), 0.0);
        $column = new ColumnDefinition('y', new Domain(Kind::Year, Field::Year, 4, 0, true), Fill::none());

        self::assertSame(2024, (new Times(new Store($context)))->year('2024-03-01', new Domain(Kind::Date, Field::Date, 10), $column));
    }

    public function testJsonWritesTheTextTheServerReturnsForTheDocument(): void
    {
        $context = new Context(new SqlModes([]), new Diagnostics(), new Variables(SystemVariables::of(GrammarRelease::MySql847), new Globals()), 0.0);
        $column = new ColumnDefinition('c', new Domain(Kind::Json, Field::Json, 4294967295, Domain::NOT_FIXED, false, Collation::known('utf8mb4_bin')), Fill::none());

        self::assertSame('{"a": [1.0, "x/y"], "b": 1}', (new Times(new Store($context)))->json('{"b":1,"a":[1.0,"x\\/y"]}', Domain::string(20, Collation::known('utf8mb4_0900_ai_ci')), $column));
    }

    public function testJsonRefusesANumberAsNoJsonText(): void
    {
        $context = new Context(new SqlModes([]), new Diagnostics(), new Variables(SystemVariables::of(GrammarRelease::MySql847), new Globals()), 0.0);
        $column = new ColumnDefinition('j', new Domain(Kind::Json, Field::Json, 4294967295, Domain::NOT_FIXED, false, Collation::known('utf8mb4_bin')), Fill::none());

        $this->expectException(SqlError::class);
        $this->expectExceptionMessage('Invalid JSON text: "not a JSON text, may need CAST" at position 0 in value for column \'t.j\'.');

        (new Times(new Store($context, 1, 't')))->json(5, Domain::integer(), $column);
    }

    public function testJsonRefusesABinaryString(): void
    {
        $context = new Context(new SqlModes([]), new Diagnostics(), new Variables(SystemVariables::of(GrammarRelease::MySql847), new Globals()), 0.0);
        $column = new ColumnDefinition('j', new Domain(Kind::Json, Field::Json, 4294967295, Domain::NOT_FIXED, false, Collation::known('utf8mb4_bin')), Fill::none());

        $this->expectException(SqlError::class);
        $this->expectExceptionMessage("Cannot create a JSON value from a string with CHARACTER SET 'binary'.");

        (new Times(new Store($context, 1, 't')))->json('{}', Domain::string(2, Collation::binary()), $column);
    }

    public function testJsonNamesTheParserMessageThePositionAndTheColumn(): void
    {
        $context = new Context(new SqlModes([]), new Diagnostics(), new Variables(SystemVariables::of(GrammarRelease::MySql847), new Globals()), 0.0);
        $column = new ColumnDefinition('j', new Domain(Kind::Json, Field::Json, 4294967295, Domain::NOT_FIXED, false, Collation::known('utf8mb4_bin')), Fill::none());

        $this->expectException(SqlError::class);
        $this->expectExceptionMessage('Invalid JSON text: "Missing a name for object member." at position 1 in value for column \'t.j\'.');

        (new Times(new Store($context, 1, 't')))->json('{bad', Domain::string(4, Collation::known('utf8mb4_0900_ai_ci')), $column);
    }

    public function testJsonRefusesADocumentTooDeepFollowedByTheParserError(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (j JSON)');

        $session->run("INSERT INTO t VALUES ('" . str_repeat('[', 101) . str_repeat(']', 101) . "')");

        self::assertSame([['Error', 3157, 'The JSON document exceeds the maximum depth.'], ['Error', 3140, 'Invalid JSON text: "Terminate parsing due to Handler error." at position 101 in value for column \'t.j\'.']], $session->diagnostics->conditions);
    }

    public function testJsonRefusesATextThatIsNoDocument(): void
    {
        $context = new Context(new SqlModes([]), new Diagnostics(), new Variables(SystemVariables::of(GrammarRelease::MySql847), new Globals()), 0.0);
        $column = new ColumnDefinition('c', new Domain(Kind::Json, Field::Json, 4294967295, Domain::NOT_FIXED, false, Collation::known('utf8mb4_bin')), Fill::none());

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(3140);

        (new Times(new Store($context)))->json('{bad', Domain::string(4, Collation::known('utf8mb4_0900_ai_ci')), $column);
    }
}
