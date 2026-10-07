<?php

declare(strict_types=1);

namespace Tests\Unit\Session;

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
    public function testTouchKeepsTheRowsAFailedStatementRestores(): void
    {
        $instance = new Instance('8.4.7', [], ['d']);
        $instance->connect()->query('CREATE TABLE d.t (a INT); INSERT INTO d.t VALUES (1)');
        $table = $instance->dictionary->table('d', 't');
        self::assertNotNull($table);
        $transaction = new Transaction($instance->dictionary);
        $transaction->beginStatement();
        $transaction->touch($table);
        $table->data->insert([2]);
        $transaction->touch($table);
        $table->data->insert([3]);
        $transaction->abortStatement();

        self::assertSame([1 => [1]], $table->data->rows);
    }

    public function testBeginStatementForgetsTheRowsOfTheLastStatement(): void
    {
        $instance = new Instance('8.4.7', [], ['d']);
        $instance->connect()->query('CREATE TABLE d.t (a INT); INSERT INTO d.t VALUES (1)');
        $table = $instance->dictionary->table('d', 't');
        self::assertNotNull($table);
        $transaction = new Transaction($instance->dictionary);
        $transaction->touch($table);
        $table->data->insert([2]);
        $transaction->beginStatement();
        $transaction->abortStatement();

        self::assertSame([1 => [1], 2 => [2]], $table->data->rows);
    }

    public function testEndStatementKeepsTheChanges(): void
    {
        $instance = new Instance('8.4.7', [], ['d']);
        $instance->connect()->query('CREATE TABLE d.t (a INT)');
        $table = $instance->dictionary->table('d', 't');
        self::assertNotNull($table);
        $transaction = new Transaction($instance->dictionary);
        $transaction->beginStatement();
        $transaction->touch($table);
        $table->data->insert([2]);
        $transaction->endStatement();
        $transaction->abortStatement();

        self::assertSame([1 => [2]], $table->data->rows);
    }

    public function testAbortStatementRestoresTheRowsOfAFailedInsert(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; CREATE TABLE d.t (a INT PRIMARY KEY); INSERT INTO d.t VALUES (2)');
        $session->run('INSERT INTO d.t VALUES (1), (2), (3)');
        $result = $session->query('SELECT a FROM d.t ORDER BY a')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['2']], $result->rows);
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
        $transaction->touch($table);
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
        $transaction->beginStatement();
        $transaction->touch($table);
        $table->data->insert([2]);
        $transaction->endStatement();
        $transaction->beginStatement();
        $transaction->touch($table);
        $table->data->delete(1);
        $transaction->endStatement();
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
}
