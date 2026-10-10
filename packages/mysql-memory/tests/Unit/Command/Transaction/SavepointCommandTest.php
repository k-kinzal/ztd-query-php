<?php

declare(strict_types=1);

namespace Tests\Unit\Command\Transaction;

use MySqlMemory\Command\Transaction\SavepointCommand;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Instance;
use MySqlMemory\Result\Completion;
use MySqlMemory\Result\ResultSet;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(SavepointCommand::class)]
#[Small]
final class SavepointCommandTest extends TestCase
{
    public function testClearsDiagnosticsAnswersTrue(): void
    {
        self::assertTrue((new SavepointCommand())->clearsDiagnostics());
    }

    public function testExecuteRollsBackToTheSavepoint(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (a INT)');
        $session->query('START TRANSACTION; INSERT INTO t VALUES (1); SAVEPOINT s1; INSERT INTO t VALUES (2); SAVEPOINT s2; INSERT INTO t VALUES (3)');
        $reply = $session->query('ROLLBACK TO SAVEPOINT s1')[0];
        $result = $session->query('SELECT a FROM t')[0];

        self::assertInstanceOf(Completion::class, $reply);
        self::assertSame(0, $reply->affectedRows);
        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['1']], $result->rows);
    }

    public function testExecuteDeletesTheSavepointsAfterTheOneRolledBackTo(): void
    {
        $session = (new Instance())->connect();
        $session->query('BEGIN; SAVEPOINT s1; SAVEPOINT s2; ROLLBACK TO s1');

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1305);
        $this->expectExceptionMessage('SAVEPOINT s2 does not exist');

        $session->query('ROLLBACK TO s2');
    }

    public function testExecuteKeepsTheChangesBeforeTheSavepointUntilRollback(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (a INT)');
        $session->query('BEGIN; INSERT INTO t VALUES (1); SAVEPOINT s; INSERT INTO t VALUES (2); ROLLBACK TO s; INSERT INTO t VALUES (4); COMMIT');
        $result = $session->query('SELECT a FROM t')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['1'], ['4']], $result->rows);
    }

    public function testExecuteSetsNoSavepointOutsideATransaction(): void
    {
        $session = (new Instance())->connect();
        $session->query('SAVEPOINT a');

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1305);
        $this->expectExceptionMessage('SAVEPOINT a does not exist');

        $session->query('ROLLBACK TO a');
    }

    public function testExecuteForgetsTheSavepointsOnCommit(): void
    {
        $session = (new Instance())->connect();
        $session->query('BEGIN; SAVEPOINT s3; COMMIT');

        $this->expectException(SqlError::class);
        $this->expectExceptionMessage('SAVEPOINT s3 does not exist');

        $session->query('ROLLBACK TO s3');
    }

    public function testExecuteReleasesTheSavepointAndTheLaterOnes(): void
    {
        $session = (new Instance())->connect();
        $session->query('BEGIN; SAVEPOINT a; SAVEPOINT b; SAVEPOINT c; RELEASE SAVEPOINT b; ROLLBACK TO a');

        $this->expectException(SqlError::class);
        $this->expectExceptionMessage('SAVEPOINT c does not exist');

        $session->query('ROLLBACK TO c');
    }

    public function testExecuteNamesAMissingSavepointAsWritten(): void
    {
        $session = (new Instance())->connect();

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1305);
        $this->expectExceptionMessage('SAVEPOINT 0"é猫Z does not exist');

        $session->query('RELEASE SAVEPOINT `0"é猫Z`');
    }

    public function testExecuteRefusesASavepointWhileAnXaTransactionIsIdle(): void
    {
        $session = (new Instance())->connect();
        $session->query("XA START 'x'; XA END 'x'");

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1399);
        $this->expectExceptionMessage('XAER_RMFAIL: The command cannot be executed when global transaction is in the  IDLE state');

        $session->query('SAVEPOINT x');
    }
    public function testExecuteWarnsThatATableThatIsNotTransactionalChangedAfterTheSavepoint(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect('root', 'localhost', 'd');
        $session->query('CREATE TABLE m (a INT) ENGINE=MyISAM; SET autocommit = 0; SAVEPOINT s; INSERT INTO m VALUES (1)');
        $reply = $session->query('ROLLBACK TO SAVEPOINT s')[0];
        $warnings = $session->query('SHOW WARNINGS')[0];

        self::assertInstanceOf(Completion::class, $reply);
        self::assertInstanceOf(ResultSet::class, $warnings);
        self::assertSame([1, [['Warning', '1196', "Some non-transactional changed tables couldn't be rolled back"]]], [$reply->warnings, $warnings->rows]);
    }
}
