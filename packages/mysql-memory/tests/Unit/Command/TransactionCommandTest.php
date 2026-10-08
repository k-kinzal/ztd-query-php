<?php

declare(strict_types=1);

namespace Tests\Unit\Command;

use MySqlMemory\Command\TransactionCommand;
use MySqlMemory\Instance;
use MySqlMemory\Result\Completion;
use MySqlMemory\Result\ResultSet;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(TransactionCommand::class)]
#[Small]
final class TransactionCommandTest extends TestCase
{
    public function testClearsDiagnosticsAnswersTrue(): void
    {
        self::assertTrue((new TransactionCommand())->clearsDiagnostics());
    }

    public function testExecuteRollsBackTheChangesOfTheTransaction(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (a INT); INSERT INTO t VALUES (1)');

        $session->query('BEGIN; INSERT INTO t VALUES (2); DELETE FROM t WHERE a = 1');
        $reply = $session->query('ROLLBACK')[0];
        $result = $session->query('SELECT a FROM t')[0];

        self::assertInstanceOf(Completion::class, $reply);
        self::assertSame([0, 0], [$reply->affectedRows, $reply->warnings]);
        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['1']], $result->rows);
    }

    public function testExecuteCommitsTheChangesOfTheTransaction(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (a INT)');

        $session->query('START TRANSACTION; INSERT INTO t VALUES (2); COMMIT; ROLLBACK');
        $result = $session->query('SELECT a FROM t')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['2']], $result->rows);
    }

    public function testExecuteCommitsTheOpenTransactionWhenAnotherBegins(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (a INT)');

        $session->query('BEGIN; INSERT INTO t VALUES (3); BEGIN; ROLLBACK');
        $result = $session->query('SELECT a FROM t')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['3']], $result->rows);
    }

    public function testExecuteLeavesAnAutocommittedChangeOutsideAnyTransaction(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (a INT)');

        $session->query('INSERT INTO t VALUES (4); ROLLBACK');
        $result = $session->query('SELECT a FROM t')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['4']], $result->rows);
    }

    public function testExecuteRefusesCommitWhileAnXaTransactionIsActive(): void
    {
        $session = (new Instance())->connect();
        $session->query("XA START 'a'");

        $this->expectException(\MySqlMemory\Error\SqlError::class);
        $this->expectExceptionCode(1399);
        $this->expectExceptionMessage('XAER_RMFAIL: The command cannot be executed when global transaction is in the  ACTIVE state');

        $session->query('COMMIT');
    }

    public function testExecuteWarnsThatTemporaryTablesAreNotRolledBack(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; START TRANSACTION; CREATE TEMPORARY TABLE y (a INT); DROP TEMPORARY TABLE y; ROLLBACK');

        $result1 = $session->query('SHOW WARNINGS')[0];
        self::assertInstanceOf(ResultSet::class, $result1);
        self::assertSame([['Warning', '1751', 'The creation of some temporary tables could not be rolled back.'], ['Warning', '1752', 'Some temporary tables were dropped, but these operations could not be rolled back.']], $result1->rows);
    }
    public function testExecuteWarnsThatATableThatIsNotTransactionalKeepsItsChanges(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect('root', 'localhost', 'd');
        $session->query('CREATE TABLE m (a INT) ENGINE=MyISAM; BEGIN; INSERT INTO m VALUES (1)');
        $reply = $session->query('ROLLBACK')[0];
        $warnings = $session->query('SHOW WARNINGS')[0];
        $rows = $session->query('SELECT a FROM m')[0];

        self::assertInstanceOf(Completion::class, $reply);
        self::assertInstanceOf(ResultSet::class, $warnings);
        self::assertInstanceOf(ResultSet::class, $rows);
        self::assertSame([1, [['Warning', '1196', "Some non-transactional changed tables couldn't be rolled back"]], [['1']]], [$reply->warnings, $warnings->rows, $rows->rows]);
    }

    public function testExecuteStartsAReadOnlyTransaction(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect('root', 'localhost', 'd');
        $session->query('CREATE TABLE t (a INT); START TRANSACTION READ ONLY, WITH CONSISTENT SNAPSHOT');

        self::assertSame([true, true, 0], [$session->transaction->readOnly, $session->transaction->explicit, $session->transaction->snapshot]);
    }

    public function testExecuteChainsATransactionWithTheCharacteristicsOfTheOneThatEnded(): void
    {
        $session = (new Instance())->connect();
        $session->query('SET TRANSACTION ISOLATION LEVEL SERIALIZABLE; START TRANSACTION READ ONLY; COMMIT AND CHAIN');

        self::assertSame([true, \MySqlMemory\Concurrency\Isolation::Serializable, true], [$session->transaction->open, $session->transaction->isolation, $session->transaction->readOnly]);
    }
}
