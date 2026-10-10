<?php

declare(strict_types=1);

namespace Tests\Unit\Session\Transaction;

use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use MySqlMemory\Session\Transaction;
use MySqlMemory\Session\Transaction\Statements;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(Statements::class)]
#[Small]
final class StatementsTest extends TestCase
{
    public function testBeginStartsWithTheRowsOfTheLastStatementKept(): void
    {
        $instance = new Instance('8.4.7', [], ['d']);
        $instance->connect()->query('CREATE TABLE d.t (a INT); INSERT INTO d.t VALUES (1)');
        $table = $instance->dictionary->table('d', 't');
        self::assertNotNull($table);
        $transaction = new Transaction($instance->dictionary);
        $transaction->write($table, $table->data->nextRow);
        $table->data->insert([2]);
        $transaction->statements->begin();
        $transaction->statements->abort();

        self::assertSame([1 => [1], 2 => [2]], $table->data->rows);
    }

    public function testEndKeepsTheChanges(): void
    {
        $instance = new Instance('8.4.7', [], ['d']);
        $instance->connect()->query('CREATE TABLE d.t (a INT)');
        $table = $instance->dictionary->table('d', 't');
        self::assertNotNull($table);
        $transaction = new Transaction($instance->dictionary);
        $transaction->statements->begin();
        $transaction->write($table, $table->data->nextRow);
        $table->data->insert([2]);
        $transaction->statements->end();
        $transaction->statements->abort();

        self::assertSame([1 => [2]], $table->data->rows);
    }

    public function testAbortRestoresTheRowsOfAFailedInsert(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; CREATE TABLE d.t (a INT PRIMARY KEY); INSERT INTO d.t VALUES (2)');
        $session->run('INSERT INTO d.t VALUES (1), (2), (3)');
        $result = $session->query('SELECT a FROM d.t ORDER BY a')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['2']], $result->rows);
    }

    public function testEndKeepsTheRowsOfAContainedStatementForTheStatementAroundIt(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (a INT)');
        $session->query('CREATE TABLE u (a INT)');
        $session->query('CREATE TRIGGER b BEFORE INSERT ON u FOR EACH ROW INSERT INTO t VALUES (NEW.a)');
        $session->query("CREATE TRIGGER c BEFORE INSERT ON u FOR EACH ROW BEGIN IF NEW.a < 0 THEN SIGNAL SQLSTATE '45000'; END IF; END");

        $session->run('INSERT INTO u VALUES (1), (-1)');

        $result1 = $session->query('SELECT * FROM t')[0];
        self::assertInstanceOf(ResultSet::class, $result1);
        $result2 = $session->query('SELECT * FROM u')[0];
        self::assertInstanceOf(ResultSet::class, $result2);
        self::assertSame([[], []], [$result1->rows, $result2->rows]);
    }

    public function testBeginOpensATransactionUnderAutocommitOff(): void
    {
        $session = (new Instance())->connect();
        $session->query('SET autocommit = 0; SAVEPOINT a');

        self::assertSame([true, false, ['a']], [$session->transaction->open, $session->transaction->active(), array_column($session->transaction->savepoints->list, 1)]);
    }

    public function testSettleCommitsTheTransactionAStatementTurningAutocommitOnLeaves(): void
    {
        $instance = new Instance('8.4.7', [], ['d']);
        $session = $instance->connect('root', 'localhost', 'd');
        $other = $instance->connect('root', 'localhost', 'd');
        $session->query('CREATE TABLE t (a INT); SET autocommit = 0; INSERT INTO t VALUES (1); SET autocommit = 1');
        $result = $other->query('SELECT a FROM t')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([[['1']], false], [$result->rows, $session->transaction->open]);
    }

    public function testUndoFromKeepsTheChangesOfStatementsThatStoodOnTheirOwn(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect('root', 'localhost', 'd');
        $session->query('CREATE TABLE t (a INT); CREATE PROCEDURE p() BEGIN INSERT INTO t VALUES (1); SELECT * FROM nosuch; END');
        $session->run('CALL p()');
        $result = $session->query('SELECT a FROM t')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['1']], $result->rows);
    }


    public function testUnrestorableMarksTheStatementRunning(): void
    {
        $transaction = new Transaction((new Instance())->dictionary);
        $transaction->statements->unrestorable();
        $transaction->statements->begin();
        $transaction->statements->unrestorable();

        self::assertSame([[0, true, true]], $transaction->statements->running);
    }
}
