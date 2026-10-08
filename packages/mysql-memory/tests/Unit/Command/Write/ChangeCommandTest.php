<?php

declare(strict_types=1);

namespace Tests\Unit\Command\Write;

use MySqlMemory\Command\Write\ChangeCommand;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Instance;
use MySqlMemory\Result\Completion;
use MySqlMemory\Result\ResultSet;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(ChangeCommand::class)]
#[Small]
final class ChangeCommandTest extends TestCase
{
    public function testClearsDiagnosticsAnswersTrue(): void
    {
        self::assertTrue((new ChangeCommand())->clearsDiagnostics());
    }

    public function testExecuteUpdatesTheRowsThatMeetTheCondition(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (id INT PRIMARY KEY, v INT); INSERT INTO t VALUES (1, 10), (2, 20), (3, 30)');

        $reply = $session->query('UPDATE t SET v = v + 1 WHERE id > 1')[0];
        $count = $session->variables->rowCount;
        $result = $session->query('SELECT id, v FROM t')[0];

        self::assertInstanceOf(Completion::class, $reply);
        self::assertSame([2, 0, 0, 'Rows matched: 2  Changed: 2  Warnings: 0'], [$reply->affectedRows, $reply->lastInsertId, $reply->warnings, $reply->info]);
        self::assertSame(2, $count);
        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['1', '10'], ['2', '21'], ['3', '31']], $result->rows);
    }

    public function testUpdateCountsTheRowsItChangedNotThoseItMatched(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (id INT PRIMARY KEY, v INT); INSERT INTO t VALUES (1, 10), (2, 20)');

        $reply = $session->query('UPDATE t SET v = 10')[0];

        self::assertInstanceOf(Completion::class, $reply);
        self::assertSame([1, 'Rows matched: 2  Changed: 1  Warnings: 0'], [$reply->affectedRows, $reply->info]);
    }

    public function testUpdateLetsEachAssignmentSeeTheOnesBeforeIt(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (a INT, b INT); INSERT INTO t VALUES (1, 0)');

        $session->query('UPDATE t SET a = a * 10, b = a + 1, a = a + 1');
        $result = $session->query('SELECT a, b FROM t')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['11', '11']], $result->rows);
    }

    public function testUpdateRefusesADuplicateKey(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (id INT PRIMARY KEY); INSERT INTO t VALUES (1), (2)');

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1062);
        $this->expectExceptionMessage("Duplicate entry '2' for key 't.PRIMARY'");

        $session->query('UPDATE t SET id = 2 WHERE id = 1');
    }

    public function testUpdateIgnoreSkipsARowWithADuplicateKeyWithAWarning(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (id INT PRIMARY KEY); INSERT INTO t VALUES (1), (2)');

        $reply = $session->query('UPDATE IGNORE t SET id = 2 WHERE id = 1')[0];
        $warnings = $session->query('SHOW WARNINGS')[0];

        self::assertInstanceOf(Completion::class, $reply);
        self::assertSame([0, 1, 'Rows matched: 1  Changed: 0  Warnings: 1'], [$reply->affectedRows, $reply->warnings, $reply->info]);
        self::assertInstanceOf(ResultSet::class, $warnings);
        self::assertSame([['Warning', '1062', "Duplicate entry '2' for key 't.PRIMARY'"]], $warnings->rows);
    }

    public function testUpdateRefusesNullForANotNullColumn(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (v INT NOT NULL); INSERT INTO t VALUES (1)');

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1048);
        $this->expectExceptionMessage("Column 'v' cannot be null");

        $session->query('UPDATE t SET v = NULL');
    }

    public function testUpdateIgnoreWritesTheImplicitDefaultForNullWithAWarning(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (v INT NOT NULL); INSERT INTO t VALUES (1)');

        $reply = $session->query('UPDATE IGNORE t SET v = NULL')[0];
        $warnings = $session->query('SHOW WARNINGS')[0];
        $result = $session->query('SELECT v FROM t')[0];

        self::assertInstanceOf(Completion::class, $reply);
        self::assertSame([1, 1], [$reply->affectedRows, $reply->warnings]);
        self::assertInstanceOf(ResultSet::class, $warnings);
        self::assertSame([['Warning', '1048', "Column 'v' cannot be null"]], $warnings->rows);
        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['0']], $result->rows);
    }

    public function testMatchedTakesTheRowsInTheOrderUpToTheLimit(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (id INT PRIMARY KEY, v INT); INSERT INTO t VALUES (1, 1), (2, 1), (3, 1)');

        $session->query('UPDATE t SET v = 0 ORDER BY id DESC LIMIT 2');
        $result = $session->query('SELECT id, v FROM t')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['1', '1'], ['2', '0'], ['3', '0']], $result->rows);
    }

    public function testExecuteDeletesTheRowsThatMeetTheCondition(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (id INT PRIMARY KEY); INSERT INTO t VALUES (1), (2), (3)');

        $reply = $session->query('DELETE FROM t WHERE id <> 2')[0];
        $count = $session->variables->rowCount;
        $result = $session->query('SELECT id FROM t')[0];

        self::assertInstanceOf(Completion::class, $reply);
        self::assertSame([2, 0, 0, ''], [$reply->affectedRows, $reply->lastInsertId, $reply->warnings, $reply->info]);
        self::assertSame(2, $count);
        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['2']], $result->rows);
    }

    public function testExecuteDeletesTheFirstRowsInTheOrder(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (id INT PRIMARY KEY, v INT); INSERT INTO t VALUES (1, 30), (2, 10), (3, 20)');

        $session->query('DELETE FROM t ORDER BY v LIMIT 2');
        $result = $session->query('SELECT id FROM t')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['1']], $result->rows);
    }

    public function testTableRefusesAMissingTable(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d');

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1146);
        $this->expectExceptionMessage("Table 'd.nope' doesn't exist");

        $session->query('DELETE FROM nope');
    }

    public function testTableRefusesAPartitionOfATableWithoutPartitions(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (a INT)');

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1747);
        $this->expectExceptionMessage('PARTITION () clause on non partitioned table');

        $session->query('DELETE FROM t PARTITION (p0)');
    }

    public function testDiffersComparesTheValuesOfEachColumn(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (a INT, b VARCHAR(3))');
        $table = $session->instance->dictionary->table('d', 't');
        $command = new ChangeCommand();

        self::assertNotNull($table);
        self::assertSame(
            [false, true, true, true],
            [$command->differs([1, 'x'], [1, 'x'], $table), $command->differs([1, 'x'], [2, 'x'], $table), $command->differs([1, 'x'], [1, null], $table), $command->differs([1, 'x'], [1, 'X'], $table)],
        );
    }

    public function testUpdateRefreshesTheColumnsOnUpdateCurrentTimestampOfTheChangedRows(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (id INT, e TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP); INSERT INTO t (id) VALUES (1), (2)');

        $session->query('UPDATE t SET id = 2');
        $result = $session->query('SELECT id, e IS NULL FROM t')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['2', '0'], ['2', '1']], $result->rows);
    }

    public function testUpdateNamesTheColumnOfAnInvalidJsonTextByTheAliasOfTheTable(): void
    {
        $session = (new Instance())->connect();
        $session->query("CREATE DATABASE d; USE d; CREATE TABLE t (j JSON); INSERT INTO t VALUES ('[]')");

        $this->expectException(SqlError::class);
        $this->expectExceptionMessage('Invalid JSON text: "Missing a name for object member." at position 1 in value for column \'x.j\'.');

        $session->query("UPDATE t AS x SET j = '{bad'");
    }

    public function testUpdateWritesAnInvisibleColumn(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE v (a INT, e INT INVISIBLE, f DECIMAL(5,2)); INSERT INTO v VALUES (1, 2.5)');

        $session->query('UPDATE v SET e = 9 WHERE e IS NULL');
        $result = $session->query('SELECT a, e, f FROM v')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['1', '9', '2.50']], $result->rows);
    }
}
