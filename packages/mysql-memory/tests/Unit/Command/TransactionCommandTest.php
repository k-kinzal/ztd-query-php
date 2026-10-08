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
}
