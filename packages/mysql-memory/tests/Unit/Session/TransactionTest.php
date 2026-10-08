<?php

declare(strict_types=1);

namespace Tests\Unit\Session;

use MySqlMemory\Concurrency\Isolation;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use MySqlMemory\Session\Transaction;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(Transaction::class)]
#[Small]
final class TransactionTest extends TestCase
{
    public function testWriteKeepsTheRowsAFailedStatementRestores(): void
    {
        $instance = new Instance('8.4.7', [], ['d']);
        $instance->connect()->query('CREATE TABLE d.t (a INT); INSERT INTO d.t VALUES (1)');
        $table = $instance->dictionary->table('d', 't');
        self::assertNotNull($table);
        $transaction = new Transaction($instance->dictionary);
        $transaction->statements->begin();
        $transaction->write($table, $table->data->nextRow);
        $table->data->insert([2]);
        $transaction->write($table, 1);
        $table->data->update(1, [3]);
        $transaction->statements->abort();

        self::assertSame([1 => [1]], $table->data->rows);
    }

    public function testWriteKeepsNothingOfATableThatIsNotTransactional(): void
    {
        $instance = new Instance('8.4.7', [], ['d']);
        $instance->connect()->query('CREATE TABLE d.t (a INT) ENGINE=MyISAM; INSERT INTO d.t VALUES (1)');
        $table = $instance->dictionary->table('d', 't');
        self::assertNotNull($table);
        $transaction = new Transaction($instance->dictionary);
        $transaction->statements->begin();
        $transaction->write($table, $table->data->nextRow);
        $table->data->insert([2]);
        $transaction->statements->abort();

        self::assertSame([[1 => [1], 2 => [2]], true], [$table->data->rows, $transaction->unrestored]);
    }

    public function testTransactionalTellsInnoDbTablesApart(): void
    {
        $instance = new Instance('8.4.7', [], ['d']);
        $instance->connect()->query('CREATE TABLE d.t (a INT); CREATE TABLE d.m (a INT) ENGINE=MEMORY');
        $innodb = $instance->dictionary->table('d', 't');
        $memory = $instance->dictionary->table('d', 'm');
        self::assertNotNull($innodb);
        self::assertNotNull($memory);

        self::assertSame([true, false], [Transaction::transactional($innodb), Transaction::transactional($memory)]);
    }

    public function testBeginOpensATransaction(): void
    {
        $transaction = new Transaction((new Instance())->dictionary);
        $transaction->begin();

        self::assertTrue($transaction->open);
    }

    public function testCommitClosesTheTransactionKeepingItsChanges(): void
    {
        $instance = new Instance('8.4.7', [], ['d']);
        $instance->connect()->query('CREATE TABLE d.t (a INT)');
        $table = $instance->dictionary->table('d', 't');
        self::assertNotNull($table);
        $transaction = new Transaction($instance->dictionary);
        $transaction->begin();
        $transaction->write($table, $table->data->nextRow);
        $table->data->insert([1]);
        $transaction->commit();
        $transaction->rollback();

        self::assertFalse($transaction->open);
        self::assertSame([1 => [1]], $table->data->rows);
    }

    public function testCommitOfAStatementKeepsTheRows(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; CREATE TABLE d.t (a INT)');
        $session->query('START TRANSACTION; INSERT INTO d.t VALUES (1); COMMIT; ROLLBACK');
        $result = $session->query('SELECT a FROM d.t')[0];

        self::assertFalse($session->transaction->open);
        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['1']], $result->rows);
    }

    public function testRollbackRestoresTheRowsTheTransactionReplaced(): void
    {
        $instance = new Instance('8.4.7', [], ['d']);
        $instance->connect()->query('CREATE TABLE d.t (a INT); INSERT INTO d.t VALUES (1)');
        $table = $instance->dictionary->table('d', 't');
        self::assertNotNull($table);
        $transaction = new Transaction($instance->dictionary);
        $transaction->begin();
        $transaction->statements->begin();
        $transaction->write($table, $table->data->nextRow);
        $table->data->insert([2]);
        $transaction->statements->end();
        $transaction->statements->begin();
        $transaction->write($table, 1);
        $table->data->delete(1);
        $transaction->statements->end();
        $transaction->rollback();

        self::assertFalse($transaction->open);
        self::assertSame([1 => [1]], $table->data->rows);
    }

    public function testRollbackOfAStatementRestoresTheRows(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; CREATE TABLE d.t (a INT); INSERT INTO d.t VALUES (1)');
        $session->query('START TRANSACTION; INSERT INTO d.t VALUES (2); UPDATE d.t SET a = a * 10');
        $open = $session->transaction->open;
        $session->query('ROLLBACK');
        $result = $session->query('SELECT a FROM d.t')[0];

        self::assertTrue($open);
        self::assertFalse($session->transaction->open);
        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['1']], $result->rows);
    }

    public function testGuardRefusesAnEndWhileAnXaTransactionIsActive(): void
    {
        $session = (new Instance())->connect();
        $session->query("XA START 'x'");

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1399);
        $this->expectExceptionMessage('XAER_RMFAIL: The command cannot be executed when global transaction is in the  ACTIVE state');

        $session->transaction->guard();
    }

    public function testRestorePutsBackTheRowsBeforeTheTransaction(): void
    {
        $instance = new Instance('8.4.7', [], ['d']);
        $session = $instance->connect();
        $session->query('CREATE TABLE d.t (a INT); INSERT INTO d.t VALUES (1); BEGIN; INSERT INTO d.t VALUES (2)');
        $session->transaction->restore();

        self::assertSame([1 => [1]], $instance->dictionary->table('d', 't')?->data->rows);
    }

    public function testDetachTakesTheChangedRowsOutOfTheTables(): void
    {
        $instance = new Instance('8.4.7', [], ['d']);
        $session = $instance->connect();
        $session->query("CREATE TABLE d.t (a INT); XA START 'x'; INSERT INTO d.t VALUES (2)");
        $changes = $session->transaction->detach();

        self::assertSame([[], 1, [2], false], [$instance->dictionary->table('d', 't')?->data->rows, $changes[0][2], $changes[0][3], $session->transaction->open]);
    }

    public function testTouchMakesTheTransactionActive(): void
    {
        $instance = new Instance('8.4.7', [], ['d']);
        $instance->connect()->query('CREATE TABLE d.t (a INT)');
        $table = $instance->dictionary->table('d', 't');
        self::assertNotNull($table);
        $transaction = new Transaction($instance->dictionary);
        $transaction->open = true;
        $transaction->touch($table);

        self::assertTrue($transaction->active());
    }

    public function testActiveTellsAnExplicitTransactionOrAnEngagedOne(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect('root', 'localhost', 'd');
        $session->query('CREATE TABLE t (a INT) ENGINE=MyISAM; SET autocommit = 0; SELECT * FROM t');
        $myisam = $session->transaction->active();
        $session->query('INSERT INTO t VALUES (1)');

        self::assertSame([false, true], [$myisam, $session->transaction->active()]);
    }

    public function testSessionIsolationReadsTheVariableOfTheRelease(): void
    {
        $session = (new Instance('5.6.51'))->connect();
        $session->query("SET SESSION tx_isolation = 'READ-COMMITTED'");

        self::assertSame([Isolation::ReadCommitted, Isolation::RepeatableRead], [$session->transaction->sessionIsolation(), (new Transaction((new Instance())->dictionary))->sessionIsolation()]);
    }

    public function testSessionReadOnlyReadsTransactionReadOnly(): void
    {
        $session = (new Instance())->connect();
        $session->query('SET SESSION transaction_read_only = 1');

        self::assertSame([true, false], [$session->transaction->sessionReadOnly(), (new Transaction((new Instance())->dictionary))->sessionReadOnly()]);
    }

    public function testAutocommitReadsTheVariable(): void
    {
        $session = (new Instance())->connect();
        $session->query('SET autocommit = 0');

        self::assertSame([false, true], [$session->transaction->autocommit(), (new Transaction((new Instance())->dictionary))->autocommit()]);
    }

    public function testForgetClosesTheReadView(): void
    {
        $instance = new Instance('8.4.7', [], ['d']);
        $session = $instance->connect();
        $session->query('START TRANSACTION WITH CONSISTENT SNAPSHOT');
        $snapshot = $session->transaction->snapshot;
        $session->transaction->forget();

        self::assertSame([0, null, []], [$snapshot, $session->transaction->snapshot, $instance->transactions->views]);
    }

    public function testDisconnectRollsBackAndReleasesTheLocks(): void
    {
        $instance = new Instance('8.4.7', [], ['d']);
        $session = $instance->connect('root', 'localhost', 'd');
        $session->query('CREATE TABLE t (a INT); BEGIN; INSERT INTO t VALUES (1)');
        $session->transaction->disconnect();
        $table = $instance->dictionary->table('d', 't');
        self::assertNotNull($table);

        self::assertSame([[], [], null], [$table->data->rows, $instance->transactions->locks->held, $instance->transactions->of($session->id)]);
    }

    public function testBeginTakesTheSnapshotAtOnceWithAConsistentSnapshot(): void
    {
        $instance = new Instance('8.4.7', [], ['d']);
        $reader = $instance->connect('root', 'localhost', 'd');
        $writer = $instance->connect('root', 'localhost', 'd');
        $reader->query('CREATE TABLE t (a INT); START TRANSACTION WITH CONSISTENT SNAPSHOT');
        $writer->query('INSERT INTO t VALUES (1)');
        $result = $reader->query('SELECT a FROM t')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([], $result->rows);
    }

    public function testEndKeepsTheChangesAndReleasesTheLocks(): void
    {
        $instance = new Instance('8.4.7', [], ['d']);
        $session = $instance->connect('root', 'localhost', 'd');
        $session->query('CREATE TABLE t (a INT); BEGIN; INSERT INTO t VALUES (1); SAVEPOINT a');
        $session->transaction->end();
        $table = $instance->dictionary->table('d', 't');
        self::assertNotNull($table);

        self::assertSame([[1 => [1]], [], false, []], [$table->data->rows, $instance->transactions->locks->held, $session->transaction->open, $session->transaction->savepoints->list]);
    }
}
