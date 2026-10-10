<?php

declare(strict_types=1);

namespace Tests\Unit\Session\Transaction;

use MySqlMemory\Concurrency\LockMode;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use MySqlMemory\Session\Transaction\RowAccess;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Query\Locking\LockedRowAction;

#[CoversClass(RowAccess::class)]
#[Small]
final class RowAccessTest extends TestCase
{
    public function testRowsAnswersTheSnapshotOfTheFirstConsistentRead(): void
    {
        $instance = new Instance('8.4.7', [], ['d']);
        $reader = $instance->connect('root', 'localhost', 'd');
        $writer = $instance->connect('root', 'localhost', 'd');
        $reader->query('CREATE TABLE t (a INT); INSERT INTO t VALUES (1); BEGIN');
        $writer->query('INSERT INTO t VALUES (2)');
        $reader->query('SELECT * FROM t');
        $writer->query('INSERT INTO t VALUES (3)');
        $table = $instance->dictionary->table('d', 't');
        self::assertNotNull($table);

        self::assertSame([[1 => [1], 2 => [2]], [1 => [1], 2 => [2], 3 => [3]]], [$reader->transaction->access->rows($table), $reader->transaction->access->rows($table, LockMode::Shared)]);
    }

    public function testRowsReadsTheLatestRowsUnderReadUncommitted(): void
    {
        $instance = new Instance('8.4.7', [], ['d']);
        $reader = $instance->connect('root', 'localhost', 'd');
        $writer = $instance->connect('root', 'localhost', 'd');
        $reader->query('CREATE TABLE t (a INT); SET SESSION TRANSACTION ISOLATION LEVEL READ UNCOMMITTED');
        $writer->query('BEGIN; INSERT INTO t VALUES (1)');
        $result = $reader->query('SELECT a FROM t')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['1']], $result->rows);
    }

    public function testCommittedAnswersTheVersionAnotherTransactionChangedARowFrom(): void
    {
        $instance = new Instance('8.4.7', [], ['d']);
        $reader = $instance->connect('root', 'localhost', 'd');
        $writer = $instance->connect('root', 'localhost', 'd');
        $reader->query('CREATE TABLE t (a INT); CREATE TEMPORARY TABLE tt (a INT); INSERT INTO t VALUES (1)');
        $writer->query('BEGIN; UPDATE t SET a = 2');
        $table = $instance->dictionary->table('d', 't');
        self::assertNotNull($table);

        self::assertSame([[[1]], null], [$reader->transaction->access->committed($table, 1), $writer->transaction->access->committed($table, 1)]);
    }

    public function testContendedTellsARowAnotherTransactionHolds(): void
    {
        $instance = new Instance('8.4.7', [], ['d']);
        $reader = $instance->connect('root', 'localhost', 'd');
        $writer = $instance->connect('root', 'localhost', 'd');
        $reader->query('CREATE TABLE t (a INT); INSERT INTO t VALUES (1), (2)');
        $writer->query('BEGIN; SELECT * FROM t WHERE a = 1 FOR SHARE');
        $table = $instance->dictionary->table('d', 't');
        self::assertNotNull($table);

        self::assertSame([true, false, false], [$reader->transaction->access->contended($table, 1, LockMode::Exclusive), $reader->transaction->access->contended($table, 1, LockMode::Shared), $reader->transaction->access->contended($table, 2, LockMode::Exclusive)]);
    }

    public function testLockTimesOutAtOnceOutsideTheListenerAndLetsTheWaitPassOnTheClock(): void
    {
        $instance = new Instance('8.4.7', [], ['d']);
        $holder = $instance->connect('root', 'localhost', 'd');
        $waiter = $instance->connect('root', 'localhost', 'd');
        $holder->query('CREATE TABLE t (a INT); INSERT INTO t VALUES (1); BEGIN; UPDATE t SET a = 2');
        $waiter->query('SET innodb_lock_wait_timeout = 7');
        $passed = $instance->registry->threads->passed;
        $replies = $waiter->run('UPDATE t SET a = 3');
        $error = $replies[0] ?? null;

        self::assertInstanceOf(SqlError::class, $error);
        self::assertSame([1205, 'Lock wait timeout exceeded; try restarting transaction', 7.0], [$error->getCode(), $error->getMessage(), $instance->registry->threads->passed - $passed]);
    }

    public function testLockAnswersFalseForARowAnotherTransactionHoldsWithSkipLocked(): void
    {
        $instance = new Instance('8.4.7', [], ['d']);
        $holder = $instance->connect('root', 'localhost', 'd');
        $waiter = $instance->connect('root', 'localhost', 'd');
        $holder->query('CREATE TABLE t (a INT); INSERT INTO t VALUES (1); BEGIN; UPDATE t SET a = 2');
        $table = $instance->dictionary->table('d', 't');
        self::assertNotNull($table);

        self::assertSame([false, true], [$waiter->transaction->access->lock($table, 1, LockMode::Shared, LockedRowAction::SkipLocked), $holder->transaction->access->lock($table, 1, LockMode::Shared)]);
    }

    public function testDeadlockRaisesErLockDeadlock(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect('root', 'localhost', 'd');
        $session->query('CREATE TABLE t (a INT); BEGIN; INSERT INTO t VALUES (1)');

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1213);
        $this->expectExceptionMessage('Deadlock found when trying to get lock; try restarting transaction');

        $session->transaction->access->deadlock();
    }

    public function testPlainReadsLockSharedUnderSerializableInsideATransaction(): void
    {
        $session = (new Instance())->connect();
        $session->query('SET SESSION TRANSACTION ISOLATION LEVEL SERIALIZABLE');
        $outside = $session->transaction->access->plainReads();
        $session->query('BEGIN');

        self::assertSame([null, LockMode::Shared], [$outside, $session->transaction->access->plainReads()]);
    }

    public function testSourceReadsLockSharedUnderRepeatableReadOnly(): void
    {
        $session = (new Instance())->connect();
        $session->query('BEGIN');
        $repeatable = $session->transaction->access->sourceReads();
        $session->query('COMMIT; SET SESSION TRANSACTION ISOLATION LEVEL READ COMMITTED; BEGIN');

        self::assertSame([LockMode::Shared, null], [$repeatable, $session->transaction->access->sourceReads()]);
    }


    public function testWaitTimesOutAtOnceOutsideTheListener(): void
    {
        $instance = new Instance('8.4.7', [], ['d']);
        $holder = $instance->connect('root', 'localhost', 'd');
        $waiter = $instance->connect('root', 'localhost', 'd');
        $holder->query('CREATE TABLE t (a INT); INSERT INTO t VALUES (1); BEGIN; UPDATE t SET a = 2');

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1205);

        $waiter->transaction->access->wait([$holder->id], null);
    }
}
