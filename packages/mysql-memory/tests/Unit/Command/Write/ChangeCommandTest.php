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

    public function testExecuteWarnsOfABadValueUnderDeleteIgnore(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect('root', 'localhost', 'd');
        $session->query('CREATE TABLE t (a INT); INSERT INTO t VALUES (1), (2)');
        $deleted = $session->query("DELETE IGNORE FROM t WHERE 'abc' XOR 1")[0];
        $warnings = $session->query('SHOW WARNINGS')[0];

        self::assertInstanceOf(Completion::class, $deleted);
        self::assertInstanceOf(ResultSet::class, $warnings);
        self::assertSame(2, $deleted->affectedRows);
        self::assertSame([['Warning', '1292', "Truncated incorrect DOUBLE value: 'abc'"]], $warnings->rows);
    }

    public function testExecuteNamesTheKeyAloneIn57(): void
    {
        $session = (new Instance('5.7.44'))->connect();
        $session->query('CREATE DATABASE d');
        $session->query('CREATE TABLE d.t (id INT PRIMARY KEY)');
        $session->query('INSERT INTO d.t VALUES (1), (2)');
        $session->query('UPDATE IGNORE d.t SET id = 1');

        self::assertSame([['Warning', 1062, "Duplicate entry '1' for key 'PRIMARY'"]], $session->diagnostics->conditions);
    }

    public function testAssignedComputesTheGeneratedColumnsOfTheChangedRow(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (a INT, b INT AS (a * 2), c INT AS (a + 1) STORED); INSERT INTO t (a) VALUES (1); UPDATE t SET a = 5, b = DEFAULT');

        $result1 = $session->query('SELECT * FROM t')[0];
        self::assertInstanceOf(ResultSet::class, $result1);
        self::assertSame([['5', '10', '6']], $result1->rows);
    }

    public function testDefaultedComputesAnExpressionDefaultFromTheRowSoFar(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE d (a INT, b INT DEFAULT (c * 10), c INT DEFAULT 5); INSERT INTO d VALUES (1, 1, 1), (2, 2, 7); UPDATE d SET c = 3, b = DEFAULT WHERE a = 1; UPDATE d SET b = DEFAULT, c = 2 WHERE a = 2');

        $result2 = $session->query('SELECT * FROM d')[0];
        self::assertInstanceOf(ResultSet::class, $result2);
        self::assertSame([['1', '30', '3'], ['2', '70', '2']], $result2->rows);
    }

    public function testDefaultedRefusesAColumnWithoutDefaultUnderAStrictMode(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE u (a INT NOT NULL); INSERT INTO u VALUES (1)');

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1364);
        $this->expectExceptionMessage("Field 'a' doesn't have a default value");

        $session->query('UPDATE u SET a = DEFAULT');
    }

    public function testNonNullStoresTheImplicitDefaultOutsideAStrictMode(): void
    {
        $session = (new Instance())->connect();
        $session->query("CREATE DATABASE d; USE d; CREATE TABLE u (a INT NOT NULL); INSERT INTO u VALUES (1); SET sql_mode = ''; UPDATE u SET a = NULL");

        $result3 = $session->query('SELECT * FROM u')[0];
        self::assertInstanceOf(ResultSet::class, $result3);
        self::assertSame([['0']], $result3->rows);
    }

    public function testDeleteKeepsARowAForeignKeyKeepsWithIgnore(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE p (id INT PRIMARY KEY); CREATE TABLE c (pid INT, CONSTRAINT fk FOREIGN KEY (pid) REFERENCES p(id)); INSERT INTO p VALUES (1), (2); INSERT INTO c VALUES (1); DELETE IGNORE FROM p');

        $result4 = $session->query('SHOW WARNINGS')[0];
        self::assertInstanceOf(ResultSet::class, $result4);
        $result5 = $session->query('SELECT * FROM p')[0];
        self::assertInstanceOf(ResultSet::class, $result5);
        self::assertSame([[['Warning', '1451', 'Cannot delete or update a parent row: a foreign key constraint fails (`d`.`c`, CONSTRAINT `fk` FOREIGN KEY (`pid`) REFERENCES `p` (`id`))']], [['1']]], [$result4->rows, $result5->rows]);
    }

    public function testMatchedReadsTheNamedPartitionsOnly(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE r (a INT) PARTITION BY RANGE (a) (PARTITION p0 VALUES LESS THAN (10), PARTITION p1 VALUES LESS THAN (20)); INSERT INTO r VALUES (1), (15); DELETE FROM r PARTITION (p0)');

        $result6 = $session->query('SELECT * FROM r')[0];
        self::assertInstanceOf(ResultSet::class, $result6);
        self::assertSame([['15']], $result6->rows);
    }
    public function testMatchedWaitsForARowAnotherTransactionHolds(): void
    {
        $instance = new Instance('8.4.7', [], ['d']);
        $first = $instance->connect('root', 'localhost', 'd');
        $second = $instance->connect('root', 'localhost', 'd');
        $first->query('CREATE TABLE t (id INT PRIMARY KEY, v INT); INSERT INTO t VALUES (1, 10), (2, 20); BEGIN; UPDATE t SET v = 11 WHERE id = 1');
        $second->query('UPDATE t SET v = 21 WHERE id = 2');

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1205);

        $second->query('DELETE FROM t WHERE v = 10');
    }

    public function testMatchedStopsAtTheLimitWithoutOrderBy(): void
    {
        $instance = new Instance('8.4.7', [], ['d']);
        $first = $instance->connect('root', 'localhost', 'd');
        $second = $instance->connect('root', 'localhost', 'd');
        $first->query('CREATE TABLE t (id INT PRIMARY KEY, v INT); INSERT INTO t VALUES (1, 10), (2, 20); BEGIN; UPDATE t SET v = 21 WHERE id = 2');
        $second->query('UPDATE t SET v = 0 LIMIT 1');
        $result = $second->query('SELECT * FROM t WHERE id = 1')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['1', '0']], $result->rows);
    }

    public function testSelectedAnswersTheOrderingValuesOfARowTheConditionSelects(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect('root', 'localhost', 'd');
        $session->query('CREATE TABLE t (a INT)');
        $table = $session->instance->dictionary->table('d', 't');
        self::assertNotNull($table);
        $frame = new \MySqlMemory\Evaluation\Frame(new \MySqlMemory\Evaluation\Context($session->modes(), $session->diagnostics, $session->variables, 0.0));
        $where = new \MySqlMemory\Evaluation\Leaf\ColumnRead(\MySqlMemory\Typing\Domain::integer(), 0);
        $command = new ChangeCommand();

        self::assertSame([[], null, null], [$command->selected($table, [1], $frame, $where, []), $command->selected($table, [0], $frame, $where, []), $command->selected($table, null, $frame, null, [])]);
    }
    public function testMatchedReadsARowAnotherTransactionHoldsSemiConsistentlyForAnUpdateUnderReadCommitted(): void
    {
        $instance = new Instance('8.4.7', [], ['d']);
        $first = $instance->connect('root', 'localhost', 'd');
        $second = $instance->connect('root', 'localhost', 'd');
        $first->query('CREATE TABLE t (id INT PRIMARY KEY, v INT); INSERT INTO t VALUES (1, 1), (2, 2); BEGIN; UPDATE t SET v = 5 WHERE id = 1');
        $reply = $second->query('SET SESSION TRANSACTION ISOLATION LEVEL READ COMMITTED; UPDATE t SET v = 6 WHERE v = 5')[1];

        self::assertInstanceOf(Completion::class, $reply);
        self::assertSame(0, $reply->affectedRows);
    }

    public function testSortedOrdersTheRowsByTheirKeysKeepingEqualRowsInOrder(): void
    {
        $matched = [[0, [1, 5]], [1, [3, 5]], [2, [1, 4]], [3, [1, 5]]];
        $keys = [[new \MySqlMemory\Evaluation\Leaf\ColumnRead(\MySqlMemory\Typing\Domain::integer(), 0), true], [new \MySqlMemory\Evaluation\Leaf\ColumnRead(\MySqlMemory\Typing\Domain::integer(), 1), false]];

        self::assertSame([[[1, [3, 5]], [2, [1, 4]], [0, [1, 5]], [3, [1, 5]]], $matched], [(new ChangeCommand())->sorted($matched, $keys), (new ChangeCommand())->sorted($matched, [])]);
    }
}
