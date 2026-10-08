<?php

declare(strict_types=1);

namespace Tests\Unit\Command\Transaction;

use MySqlMemory\Command\Transaction\XaCommand;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Instance;
use MySqlMemory\Registry\PreparedBranch;
use MySqlMemory\Result\ResultSet;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Server\Transaction\Xa\XaStart;

#[CoversClass(XaCommand::class)]
#[Small]
final class XaCommandTest extends TestCase
{
    public function testClearsDiagnosticsAnswersTrue(): void
    {
        self::assertTrue((new XaCommand())->clearsDiagnostics());
    }

    public function testExecuteCommitsAnIdleTransactionInOnePhase(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (a INT)');
        $session->query("XA START 'q'; INSERT INTO t VALUES (3); XA END 'q'; XA COMMIT 'q' ONE PHASE; ROLLBACK");
        $result = $session->query('SELECT a FROM t')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['3']], $result->rows);
    }

    public function testStartRefusesAnOpenPlainTransaction(): void
    {
        $session = (new Instance())->connect();
        $session->query('BEGIN');

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1400);
        $this->expectExceptionMessage('XAER_OUTSIDE: Some work is done outside global transaction');

        $session->query("XA START 'a'");
    }

    public function testStartRefusesJoinFirst(): void
    {
        $session = (new Instance())->connect();
        $session->query("XA START 'a'");

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1398);
        $this->expectExceptionMessage('XAER_INVAL: Invalid arguments (or unsupported command)');

        $session->query("XA START 'b' RESUME");
    }

    public function testStartRefusesAPreparedXid(): void
    {
        $session = (new Instance())->connect();
        $session->query("XA START 'a'; XA END 'a'; XA PREPARE 'a'");

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1440);
        $this->expectExceptionMessage('XAER_DUPID: The XID already exists');

        $session->query("XA START 'a'");
    }

    public function testEndRefusesAnotherXid(): void
    {
        $session = (new Instance())->connect();
        $session->query("XA START 'a'");

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1397);
        $this->expectExceptionMessage('XAER_NOTA: Unknown XID');

        $session->query("XA END 'b'");
    }

    public function testEndRefusesASessionWithoutXaTransaction(): void
    {
        $session = (new Instance())->connect();

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1399);
        $this->expectExceptionMessage('XAER_RMFAIL: The command cannot be executed when global transaction is in the  NON-EXISTING state');

        $session->query("XA END 'y'");
    }

    public function testPrepareHidesTheChangesUntilAnotherSessionCommits(): void
    {
        $instance = new Instance('8.4.7', [], ['d']);
        $first = $instance->connect();
        $second = $instance->connect();
        $first->query("CREATE TABLE d.t (a INT); XA START 'a'; INSERT INTO d.t VALUES (1); XA END 'a'; XA PREPARE 'a'");
        $hidden = $second->query('SELECT a FROM d.t')[0];
        $second->query("XA COMMIT 'a'");
        $shown = $first->query('SELECT a FROM d.t')[0];

        self::assertInstanceOf(ResultSet::class, $hidden);
        self::assertInstanceOf(ResultSet::class, $shown);
        self::assertSame([[], [['1']]], [$hidden->rows, $shown->rows]);
    }

    public function testPrepareRefusesAnActiveTransaction(): void
    {
        $session = (new Instance())->connect();
        $session->query("XA START 'a'");

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1399);
        $this->expectExceptionMessage('in the  ACTIVE state');

        $session->query("XA PREPARE 'a'");
    }

    public function testFinishRollsBackAPreparedBranchFromAnotherSession(): void
    {
        $instance = new Instance('8.4.7', [], ['d']);
        $first = $instance->connect();
        $first->query("CREATE TABLE d.t (a INT); XA START 'r'; INSERT INTO d.t VALUES (1); XA END 'r'; XA PREPARE 'r'");
        $instance->connect()->query("XA ROLLBACK 'r'");
        $result = $first->query('SELECT a FROM d.t')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([[], []], [$result->rows, $instance->registry->prepared]);
    }

    public function testFinishRefusesADetachedBranchInsideAPlainTransaction(): void
    {
        $session = (new Instance())->connect();
        $session->query("XA START 'a'; XA END 'a'; XA PREPARE 'a'; BEGIN");

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1399);
        $this->expectExceptionMessage('in the  NON-EXISTING state');

        $session->query("XA ROLLBACK 'a'");
    }

    public function testFinishRefusesAnotherXidWhileIdle(): void
    {
        $session = (new Instance())->connect();
        $session->query("XA START 'a'; XA END 'a'");

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1399);
        $this->expectExceptionMessage('in the  IDLE state');

        $session->query("XA ROLLBACK 'b'");
    }

    public function testFinishRefusesAnUnknownXid(): void
    {
        $session = (new Instance())->connect();

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1397);

        $session->query("XA COMMIT 'zz'");
    }

    public function testRecoverListsThePreparedBranches(): void
    {
        $session = (new Instance())->connect();
        $session->query("XA START 0x41, 0x42, 3; XA END 0x41, 0x42, 3; XA PREPARE 0x41, 0x42, 3; XA START 'admin_s'; XA END 'admin_s'; XA PREPARE 'admin_s'");
        $plain = $session->query('XA RECOVER')[0];
        $converted = $session->query('XA RECOVER CONVERT XID')[0];

        self::assertInstanceOf(ResultSet::class, $plain);
        self::assertInstanceOf(ResultSet::class, $converted);
        self::assertSame([['3', '1', '1', 'AB'], ['1', '7', '0', 'admin_s']], $plain->rows);
        self::assertSame([['3', '1', '1', '0x4142'], ['1', '7', '0', '0x61646D696E5F73']], $converted->rows);
        self::assertSame([['formatID', 12], ['gtrid_length', 12], ['bqual_length', 12], ['data', 1032]], array_map(static fn ($column): array => [$column->name, $column->length], $plain->columns));
    }

    public function testPartsDefaultsTheFormatToOne(): void
    {
        $statement = (new Instance())->connect()->analyze("XA START X'00ff', 'b'")->statement;
        self::assertInstanceOf(XaStart::class, $statement);

        self::assertSame([1, "\x00\xff", 'b'], (new XaCommand())->parts($statement->xid));
    }

    public function testKeyIsThatOfThePreparedBranch(): void
    {
        $statement = (new Instance())->connect()->analyze("XA START 'g', 'b', 5")->statement;
        self::assertInstanceOf(XaStart::class, $statement);

        self::assertSame(PreparedBranch::key(5, 'g', 'b'), (new XaCommand())->key($statement->xid));
    }

    public function testRecoverSendsNumbersOf11CharactersIn57(): void
    {
        $result = (new Instance('5.7.44'))->connect()->query('XA RECOVER')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([11, 11, 11], [$result->columns[0]->length, $result->columns[1]->length, $result->columns[2]->length]);
    }

    public function testRecoverSendsData128CharactersLongIn56(): void
    {
        $result = (new Instance('5.6.51', ['character_set_results' => 'latin1']))->connect()->query('XA RECOVER')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame(128, $result->columns[3]->length);
    }
    public function testFinishCommitsThePreparedRowsKeepingTheChangesOfOtherSessions(): void
    {
        $instance = new Instance('8.4.7', [], ['d']);
        $first = $instance->connect('root', 'localhost', 'd');
        $second = $instance->connect('root', 'localhost', 'd');
        $first->query("CREATE TABLE t (a INT); XA START 'x'; INSERT INTO t VALUES (1); XA END 'x'; XA PREPARE 'x'");
        $second->query("INSERT INTO t VALUES (2); XA COMMIT 'x'");
        $result = $second->query('SELECT a FROM t ORDER BY a')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['1'], ['2']], $result->rows);
    }
}
