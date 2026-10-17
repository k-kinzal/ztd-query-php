<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Compile;

use MySqlMemory\Error\SqlError;
use MySqlMemory\Evaluation\Compile\FieldStrings;
use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(FieldStrings::class)]
#[Small]
final class FieldStringsTest extends TestCase
{
    public function testCheckRefusesAStringThatHoldsNoDateInMySql84(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect('root', 'localhost', 'd');
        $session->query('CREATE TABLE t (e DATE)');

        $error = $session->run("SELECT e = 'x' FROM t")[0];

        self::assertInstanceOf(SqlError::class, $error);
        self::assertSame([1525, "Incorrect DATE value: 'x'"], [$error->getCode(), $error->getMessage()]);
        self::assertSame([['Warning', 1292, "Incorrect date value: 'x' for column 'e' at row 1"], ['Error', 1525, "Incorrect DATE value: 'x'"]], $session->diagnostics->conditions);
    }

    public function testCheckNamesATimestampColumnInTheError(): void
    {
        $session = (new Instance('8.0.44', [], ['d']))->connect('root', 'localhost', 'd');
        $session->query('CREATE TABLE t (ts TIMESTAMP NULL)');

        $this->expectExceptionMessage("Incorrect TIMESTAMP value: '2020-02-30'");

        $session->query("SELECT ts < '2020-02-30' FROM t");
    }

    public function testCheckOnlyWarnsInMySql57(): void
    {
        $session = (new Instance('5.7.44', [], ['d']))->connect('root', 'localhost', 'd');
        $session->query('CREATE TABLE t (d DATETIME)');
        $session->query("INSERT INTO t VALUES ('2020-01-01 00:00:00')");

        $result = $session->query("SELECT d = 'x' FROM t")[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([[['0']], [['Warning', 1292, "Incorrect datetime value: 'x' for column 'd' at row 1"]]], [$result->rows, $session->diagnostics->conditions]);
    }

    public function testCheckComparesTheDateBeforeMoreTextAndWarns(): void
    {
        $session = (new Instance('9.1.0', [], ['d']))->connect('root', 'localhost', 'd');
        $session->query('CREATE TABLE t (e DATE)');
        $session->query("INSERT INTO t VALUES ('2020-01-01')");

        $result = $session->query("SELECT e = '2020-01-01x' FROM t")[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([[['1']], [['Warning', 1292, "Incorrect date value: '2020-01-01x' for column 'e' at row 1"]]], [$result->rows, $session->diagnostics->conditions]);
    }

    public function testCheckOnlyWarnsForInAndBetween(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect('root', 'localhost', 'd');
        $session->query('CREATE TABLE t (e DATE)');
        $session->query("INSERT INTO t VALUES ('2020-01-01')");

        $result = $session->query("SELECT e IN ('x', '2020-01-01'), e BETWEEN 'y' AND 'z' FROM t")[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([[['1', '0']], [
            ['Warning', 1292, "Incorrect date value: 'x' for column 'e' at row 1"],
            ['Warning', 1292, "Incorrect date value: 'y' for column 'e' at row 1"],
            ['Warning', 1292, "Incorrect date value: 'z' for column 'e' at row 1"],
        ]], [$result->rows, $session->diagnostics->conditions]);
    }

    public function testCheckTakesAValidDate(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect('root', 'localhost', 'd');
        $session->query('CREATE TABLE t (e DATE)');

        $session->query("SELECT e = '2020-1-1', e > '2020-01-01 10:00:00' FROM t");

        self::assertSame([], $session->diagnostics->conditions);
    }

    public function testMomentComparesADateWithMoreTextAsTheDateAndWarns(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect('root', 'localhost', 'd');
        $session->query('CREATE TABLE t (d DATE)');
        $session->query("INSERT INTO t VALUES ('2020-01-01')");

        $result = $session->query("SELECT d = '2020-01-01 junk' FROM t")[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([[['1']], [['Warning', 1292, "Incorrect date value: '2020-01-01 junk' for column 'd' at row 1"]]], [$result->rows, $session->diagnostics->conditions]);
    }

    public function testTimeWarnsAboutAStringThatHoldsNoTime(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect('root', 'localhost', 'd');
        $session->query('CREATE TABLE t (tm TIME)');

        $session->query("SELECT tm = 'x', tm = '', tm = '10', tm = '2020-01-01 10:00:00' FROM t");

        self::assertSame([['Warning', 1292, "Incorrect time value: 'x' for column 'tm' at row 1"], ['Note', 1292, "Incorrect time value: '2020-01-01 10:00:00' for column 'tm' at row 1"]], $session->diagnostics->conditions);
    }

    public function testTimeDoesNotNoteADatetimeInMySql56(): void
    {
        $session = (new Instance('5.6.51', [], ['d']))->connect('root', 'localhost', 'd');
        $session->query('CREATE TABLE t (tm TIME)');

        $session->query("SELECT tm = '2020-01-01 10:00:00' FROM t");

        self::assertSame([], $session->diagnostics->conditions);
    }

    public function testWarnNamesTheTypeTheValueAndTheColumn(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect('root', 'localhost', 'd');
        $session->query('CREATE TABLE t (d DATETIME)');

        $session->query("SELECT d = '2020-01-01 10:00:00 junk' FROM t");

        self::assertSame([['Warning', 1292, "Incorrect datetime value: '2020-01-01 10:00:00 junk' for column 'd' at row 1"]], $session->diagnostics->conditions);
    }
}
