<?php

declare(strict_types=1);

namespace Tests\Unit\Command\Write;

use MySqlMemory\Command\Write\MultipleChangeCommand;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Instance;
use MySqlMemory\Result\Completion;
use MySqlMemory\Result\ResultSet;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Dml\Update;

#[CoversClass(MultipleChangeCommand::class)]
#[Small]
final class MultipleChangeCommandTest extends TestCase
{
    public function testClearsDiagnosticsAnswersTrue(): void
    {
        self::assertTrue((new MultipleChangeCommand())->clearsDiagnostics());
    }

    public function testExecuteChangesEachJoinedRowOnce(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE a (id INT PRIMARY KEY, v INT); CREATE TABLE b (id INT PRIMARY KEY, a_id INT); INSERT INTO a VALUES (1, 10), (2, 20), (3, 30); INSERT INTO b VALUES (1, 1), (2, 1), (3, 2)');

        $reply = $session->query('UPDATE a JOIN b ON b.a_id = a.id SET a.v = a.v + 1')[0];
        $count = $session->variables->rowCount;
        $result = $session->query('SELECT id, v FROM a')[0];

        self::assertInstanceOf(Completion::class, $reply);
        self::assertSame([2, 0, 'Rows matched: 2  Changed: 2  Warnings: 0'], [$reply->affectedRows, $reply->warnings, $reply->info]);
        self::assertSame(2, $count);
        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['1', '11'], ['2', '21'], ['3', '30']], $result->rows);
    }

    public function testUpdateChangesTheRowsOfEveryTableItAssigns(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE a (id INT PRIMARY KEY, v INT); CREATE TABLE b (id INT PRIMARY KEY, a_id INT); INSERT INTO a VALUES (1, 10), (2, 20); INSERT INTO b VALUES (1, 1), (2, 2)');

        $reply = $session->query('UPDATE a, b SET a.v = 0, b.a_id = 9 WHERE a.id = b.a_id AND a.id = 2')[0];
        $first = $session->query('SELECT id, v FROM a')[0];
        $second = $session->query('SELECT id, a_id FROM b')[0];

        self::assertInstanceOf(Completion::class, $reply);
        self::assertSame([2, 'Rows matched: 2  Changed: 2  Warnings: 0'], [$reply->affectedRows, $reply->info]);
        self::assertInstanceOf(ResultSet::class, $first);
        self::assertSame([['1', '10'], ['2', '0']], $first->rows);
        self::assertInstanceOf(ResultSet::class, $second);
        self::assertSame([['1', '1'], ['2', '9']], $second->rows);
    }

    public function testApplyRefusesADuplicateKey(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE a (id INT PRIMARY KEY); CREATE TABLE b (id INT PRIMARY KEY); INSERT INTO a VALUES (1), (3); INSERT INTO b VALUES (1)');

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1062);
        $this->expectExceptionMessage("Duplicate entry '3' for key 'a.PRIMARY'");

        $session->query('UPDATE a JOIN b ON b.id = a.id SET a.id = 3');
    }

    public function testApplySkipsADuplicateKeyWithAWarningForIgnore(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE a (id INT PRIMARY KEY); CREATE TABLE b (id INT PRIMARY KEY); INSERT INTO a VALUES (1), (3); INSERT INTO b VALUES (1)');

        $reply = $session->query('UPDATE IGNORE a JOIN b ON b.id = a.id SET a.id = 3')[0];
        $warnings = $session->query('SHOW WARNINGS')[0];

        self::assertInstanceOf(Completion::class, $reply);
        self::assertSame([0, 1, 'Rows matched: 1  Changed: 0  Warnings: 1'], [$reply->affectedRows, $reply->warnings, $reply->info]);
        self::assertInstanceOf(ResultSet::class, $warnings);
        self::assertSame([['Warning', '1062', "Duplicate entry '3' for key 'a.PRIMARY'"]], $warnings->rows);
    }

    public function testDeleteRemovesTheRowsOfEachTargetTable(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE a (id INT PRIMARY KEY); CREATE TABLE b (id INT PRIMARY KEY, a_id INT); INSERT INTO a VALUES (1), (2), (3); INSERT INTO b VALUES (1, 1), (2, 1), (3, 9)');

        $reply = $session->query('DELETE a, b FROM a JOIN b ON b.a_id = a.id')[0];
        $first = $session->query('SELECT id FROM a')[0];
        $second = $session->query('SELECT id FROM b')[0];

        self::assertInstanceOf(Completion::class, $reply);
        self::assertSame([3, 0, ''], [$reply->affectedRows, $reply->warnings, $reply->info]);
        self::assertInstanceOf(ResultSet::class, $first);
        self::assertSame([['2'], ['3']], $first->rows);
        self::assertInstanceOf(ResultSet::class, $second);
        self::assertSame([['3']], $second->rows);
    }

    public function testOccurrenceFindsATargetByItsAlias(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE a (id INT PRIMARY KEY); INSERT INTO a VALUES (1), (2), (3)');

        $reply = $session->query('DELETE x FROM a AS x WHERE x.id = 3')[0];
        $result = $session->query('SELECT id FROM a')[0];

        self::assertInstanceOf(Completion::class, $reply);
        self::assertSame(1, $reply->affectedRows);
        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['1'], ['2']], $result->rows);
    }

    public function testDeleteRefusesATargetTheStatementDoesNotRead(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE a (id INT)');

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1109);
        $this->expectExceptionMessage("Unknown table 'nope' in MULTI DELETE");

        $session->query('DELETE nope FROM a');
    }

    public function testJoinedTellsWhetherAnUpdateWritesThroughAJoin(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE a (id INT); CREATE TABLE b (id INT)');
        $single = $session->analyze('UPDATE a SET id = 1')->statement;
        $list = $session->analyze('UPDATE a, b SET a.id = 1')->statement;
        $join = $session->analyze('UPDATE a JOIN b ON a.id = b.id SET a.id = 1')->statement;

        self::assertInstanceOf(Update::class, $single);
        self::assertInstanceOf(Update::class, $list);
        self::assertInstanceOf(Update::class, $join);
        self::assertSame([false, true, true], [MultipleChangeCommand::joined($single), MultipleChangeCommand::joined($list), MultipleChangeCommand::joined($join)]);
    }

    public function testApplyStoresTheImplicitDefaultForNullOutsideStrictMode(): void
    {
        $session = (new Instance())->connect();
        $session->query("SET sql_mode = ''; CREATE DATABASE d; USE d; CREATE TABLE w (id INT PRIMARY KEY, a INT NOT NULL); CREATE TABLE u (b INT); INSERT INTO w VALUES (1, 5); INSERT INTO u VALUES (1)");

        $session->query('UPDATE w, u SET w.a = NULL');
        $conditions = $session->diagnostics->conditions;
        $result = $session->query('SELECT id, a FROM w')[0];

        self::assertSame([['Warning', 1048, "Column 'a' cannot be null"]], $conditions);
        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['1', '0']], $result->rows);
    }

    public function testApplyRefusesNullForANotNullColumnUnderAStrictMode(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE w (id INT PRIMARY KEY, a INT NOT NULL); CREATE TABLE u (b INT); INSERT INTO w VALUES (1, 5); INSERT INTO u VALUES (1)');

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1048);

        $session->query('UPDATE w, u SET w.a = NULL');
    }

    public function testDirectNamesTheFirstTableOfAJoinOfTablesThatReadsItOnce(): void
    {
        $session = (new Instance())->connect();
        $session->query("CREATE DATABASE d; USE d; CREATE TABLE t (j JSON); CREATE TABLE u (j JSON); INSERT INTO t VALUES ('[]'); INSERT INTO u VALUES ('[]')");

        $session->run("UPDATE t AS x, u SET x.j = '['");
        $first = $session->diagnostics->conditions;
        $session->run("UPDATE t, u SET u.j = '['");
        $second = $session->diagnostics->conditions;
        $session->run("UPDATE t AS x, t AS y SET x.j = '['");

        self::assertSame([[['Error', 3140, 'Invalid JSON text: "Invalid value." at position 1 in value for column \'x.j\'.']], [['Error', 3140, 'Invalid JSON text: "Invalid value." at position 1 in value for column \'.j\'.']], [['Error', 3140, 'Invalid JSON text: "Invalid value." at position 1 in value for column \'.j\'.']]], [$first, $second, $session->diagnostics->conditions]);
    }

    public function testApplyRefreshesTheColumnsOnUpdateCurrentTimestamp(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE w (id INT, e TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP); CREATE TABLE u (b INT); INSERT INTO w (id) VALUES (1); INSERT INTO u VALUES (1)');

        $session->query('UPDATE w, u SET w.id = 2');
        $result = $session->query('SELECT id, e IS NULL FROM w')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['2', '0']], $result->rows);
    }

    public function testExecuteWarnsOfABadValueUnderAMultipleTableDeleteIgnore(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect('root', 'localhost', 'd');
        $session->query('CREATE TABLE t (a INT); INSERT INTO t VALUES (1), (2)');
        $deleted = $session->query("DELETE IGNORE t FROM t WHERE 'abc' XOR 1")[0];
        $warnings = $session->query('SHOW WARNINGS')[0];

        self::assertInstanceOf(Completion::class, $deleted);
        self::assertInstanceOf(ResultSet::class, $warnings);
        self::assertSame(2, $deleted->affectedRows);
        self::assertSame([['Warning', '1292', "Truncated incorrect DOUBLE value: 'abc'"]], $warnings->rows);
    }

    public function testExecuteLeavesOutADuplicateSilentlyIn56(): void
    {
        $session = (new Instance('5.6.51'))->connect();
        $session->query('CREATE DATABASE d');
        $session->query('CREATE TABLE d.t (id INT PRIMARY KEY)');
        $session->query('CREATE TABLE d.u (id INT)');
        $session->query('INSERT INTO d.t VALUES (1), (2)');
        $session->query('INSERT INTO d.u VALUES (1)');
        $session->query('UPDATE IGNORE d.t, d.u SET d.t.id = 1 WHERE d.u.id = 1');

        self::assertSame([], $session->diagnostics->conditions);
    }
}
