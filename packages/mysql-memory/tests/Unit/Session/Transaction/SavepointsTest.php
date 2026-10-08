<?php

declare(strict_types=1);

namespace Tests\Unit\Session\Transaction;

use MySqlMemory\Error\SqlError;
use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use MySqlMemory\Session\Transaction;
use MySqlMemory\Session\Transaction\Savepoints;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(Savepoints::class)]
#[Small]
final class SavepointsTest extends TestCase
{
    public function testSetSetsNothingOutsideATransaction(): void
    {
        $transaction = new Transaction((new Instance())->dictionary);
        $transaction->savepoints->set('a');

        self::assertSame([], $transaction->savepoints->list);
    }

    public function testSetReplacesAnEarlierSavepointOfTheSameName(): void
    {
        $session = (new Instance())->connect();
        $session->query('BEGIN; SAVEPOINT A; SAVEPOINT b; SAVEPOINT a');

        self::assertSame(['b', 'a'], array_column($session->transaction->savepoints->list, 1));
    }

    public function testFindComparesNamesWithoutRegardToCaseAndAccents(): void
    {
        $session = (new Instance())->connect();
        $session->query('BEGIN; SAVEPOINT e; SAVEPOINT x');

        self::assertSame([0, 0, null], [$session->transaction->savepoints->find('É'), $session->transaction->savepoints->find('E'), $session->transaction->savepoints->find('e ')]);
    }

    public function testRollbackToRestoresTheRowsAtTheSavepoint(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect();
        $session->query('CREATE TABLE d.t (a INT); BEGIN; INSERT INTO d.t VALUES (1); SAVEPOINT s1; INSERT INTO d.t VALUES (2); SAVEPOINT s2; INSERT INTO d.t VALUES (3)');
        $session->transaction->savepoints->rollbackTo('S1');
        $result = $session->query('SELECT a FROM d.t')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([[['1']], ['s1']], [$result->rows, array_column($session->transaction->savepoints->list, 1)]);
    }

    public function testRollbackToRefusesAMissingSavepoint(): void
    {
        $transaction = new Transaction((new Instance())->dictionary);

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1305);
        $this->expectExceptionMessage('SAVEPOINT a does not exist');

        $transaction->savepoints->rollbackTo('a');
    }

    public function testReleaseDeletesTheSavepointsSetAfterIt(): void
    {
        $session = (new Instance())->connect();
        $session->query('BEGIN; SAVEPOINT a; SAVEPOINT b; SAVEPOINT c');
        $session->transaction->savepoints->release('b');

        self::assertSame(['a'], array_column($session->transaction->savepoints->list, 1));
    }

    public function testRollbackToAnswersWhetherATableThatIsNotTransactionalChangedAfterTheSavepoint(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect('root', 'localhost', 'd');
        $session->query('CREATE TABLE t (a INT); CREATE TABLE m (a INT) ENGINE=MyISAM; BEGIN; SAVEPOINT a; INSERT INTO t VALUES (1); SAVEPOINT b; INSERT INTO m VALUES (1)');

        self::assertSame([true, true], [$session->transaction->savepoints->rollbackTo('a'), $session->transaction->savepoints->rollbackTo('a')]);
    }
}
