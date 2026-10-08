<?php

declare(strict_types=1);

namespace Tests\Unit\Command\Write;

use MySqlMemory\Command\Write\InsertCommand;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Instance;
use MySqlMemory\Result\Completion;
use MySqlMemory\Result\ResultSet;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(InsertCommand::class)]
#[Small]
final class InsertCommandTest extends TestCase
{
    public function testClearsDiagnosticsAnswersTrue(): void
    {
        self::assertTrue((new InsertCommand())->clearsDiagnostics());
    }

    public function testExecuteInsertsOneRowWithoutInfo(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (a INT, b VARCHAR(3))');

        $reply = $session->query("INSERT INTO t VALUES (1, 'x')")[0];

        self::assertInstanceOf(Completion::class, $reply);
        self::assertSame([1, 0, 0, ''], [$reply->affectedRows, $reply->lastInsertId, $reply->warnings, $reply->info]);
        self::assertSame(1, $session->variables->rowCount);
    }

    public function testExecuteInsertsSeveralRowsWithTheirRecordCount(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (a INT)');

        $reply = $session->query('INSERT INTO t VALUES (1), (2), (3)')[0];

        self::assertInstanceOf(Completion::class, $reply);
        self::assertSame([3, 'Records: 3  Duplicates: 0  Warnings: 0'], [$reply->affectedRows, $reply->info]);
    }

    public function testExecuteAnswersTheFirstGeneratedAutoIncrementValue(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (id INT AUTO_INCREMENT PRIMARY KEY, v INT); INSERT INTO t (v) VALUES (0)');

        $reply = $session->query('INSERT INTO t (v) VALUES (1), (2)')[0];
        $result = $session->query('SELECT LAST_INSERT_ID()')[0];

        self::assertInstanceOf(Completion::class, $reply);
        self::assertSame([2, 2], [$reply->affectedRows, $reply->lastInsertId]);
        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['2']], $result->rows);
    }

    public function testTableRefusesAMissingTable(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d');

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1146);
        $this->expectExceptionMessage("Table 'd.nope' doesn't exist");

        $session->query('INSERT INTO nope VALUES (1)');
    }

    public function testTableRefusesAPartitionOfATableWithoutPartitions(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (a INT)');

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1747);
        $this->expectExceptionMessage('PARTITION () clause on non partitioned table');

        $session->query('INSERT INTO t PARTITION (p0) VALUES (1)');
    }

    public function testSourcesWritesTheColumnsANamedListGives(): void
    {
        $session = (new Instance())->connect();
        $session->query("CREATE DATABASE d; USE d; CREATE TABLE t (a INT, b INT DEFAULT 7, c VARCHAR(3) DEFAULT 'z')");

        $session->query('INSERT INTO t (C, a) VALUES (\'x\', 1)');
        $result = $session->query('SELECT a, b, c FROM t')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['1', '7', 'x']], $result->rows);
    }

    public function testSourcesWritesTheColumnsOfSet(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (a INT, b INT DEFAULT 7)');

        $session->query('INSERT INTO t SET a = 1');
        $result = $session->query('SELECT a, b FROM t')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['1', '7']], $result->rows);
    }

    public function testSourcesWritesDefaultForEveryColumnOfAnEmptyRow(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (a INT DEFAULT 1, b INT DEFAULT 2)');

        $session->query('INSERT INTO t VALUES (), (); INSERT INTO t VALUES (DEFAULT, 3)');
        $result = $session->query('SELECT a, b FROM t')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['1', '2'], ['1', '2'], ['1', '3']], $result->rows);
    }

    public function testSourcesWritesEveryRowOfAValuesSourceWithDefaults(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (a INT DEFAULT 1, b INT DEFAULT 2)');

        $session->query('INSERT INTO t (a, b) (VALUES ROW(DEFAULT, 5), ROW(6, 7)) LIMIT 1; INSERT INTO t () WITH x AS (SELECT 1) VALUES ROW()');
        $result = $session->query('SELECT a, b FROM t')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['1', '5'], ['6', '7'], ['1', '2']], $result->rows);
    }

    public function testSourcesRefusesAColumnTheTableDoesNotHave(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (a INT)');

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1054);
        $this->expectExceptionMessage("Unknown column 'nope' in 'field list'");

        $session->query('INSERT INTO t (a, nope) VALUES (1, 2)');
    }

    public function testQueriedInsertsTheRowsOfAQuery(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (a INT, b INT); INSERT INTO t VALUES (1, 10), (2, 20)');

        $reply = $session->query('INSERT INTO t (a, b) SELECT a + 10, b FROM t')[0];
        $result = $session->query('SELECT a, b FROM t ORDER BY a')[0];

        self::assertInstanceOf(Completion::class, $reply);
        self::assertSame([2, 'Records: 2  Duplicates: 0  Warnings: 0'], [$reply->affectedRows, $reply->info]);
        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['1', '10'], ['2', '20'], ['11', '10'], ['12', '20']], $result->rows);
    }

    public function testQueriedRefusesAQueryOfAnotherWidth(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (a INT, b INT)');

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1136);
        $this->expectExceptionMessage("Column count doesn't match value count at row 1");

        $session->query('INSERT INTO t (a, b) SELECT 1, 2, 3');
    }

    public function testExecuteStoresTheImplicitDefaultForNullFromAQueryOutsideStrictMode(): void
    {
        $session = (new Instance())->connect();
        $session->query("SET sql_mode = ''; CREATE DATABASE d; USE d; CREATE TABLE w (a INT NOT NULL)");

        $session->query('INSERT INTO w SELECT NULL');
        $conditions = $session->diagnostics->conditions;
        $result = $session->query('SELECT a FROM w')[0];

        self::assertSame([['Warning', 1048, "Column 'a' cannot be null"]], $conditions);
        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['0']], $result->rows);
    }

    public function testSourcesWritesTheInvisibleColumnsAColumnListNames(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE v (a INT, e INT INVISIBLE, f DECIMAL(5,2))');

        $session->query('INSERT INTO v (a, e, f) VALUES (1, 2, 3.5); INSERT INTO v VALUES (4, 5.5); INSERT INTO v SET e = 3');
        $result = $session->query('SELECT a, e, f FROM v')[0];
        $star = $session->query('SELECT * FROM v')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['1', '2', '3.50'], ['4', null, '5.50'], [null, '3', null]], $result->rows);
        self::assertInstanceOf(ResultSet::class, $star);
        self::assertSame([['1', '3.50'], ['4', '5.50'], [null, null]], $star->rows);
    }
}
