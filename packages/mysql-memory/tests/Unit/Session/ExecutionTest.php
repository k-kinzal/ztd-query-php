<?php

declare(strict_types=1);

namespace Tests\Unit\Session;

use MySqlMemory\Command\Dispatcher;
use MySqlMemory\Instance;
use MySqlMemory\Session\Execution;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(Execution::class)]
#[Small]
final class ExecutionTest extends TestCase
{
    public function testPerformRunsAnAnalyzedStatementAndSetsRowCount(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (a INT)');
        $operation = $session->analyze('INSERT INTO t VALUES (1), (2)');

        (new Execution($session))->perform($operation, (new Dispatcher())->command($operation->statement));

        self::assertSame(2, $session->variables->rowCount);
    }

    public function testPerformAnswersTheRowCountOfTheLastStatementOfACall(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (a INT)');
        $session->query('CREATE PROCEDURE p() BEGIN SELECT 1; INSERT INTO t VALUES (1), (2); END');

        $session->query('CALL p()');

        self::assertSame(2, $session->variables->rowCount);
    }

    public function testRunRaisesTheWarningsOfTheHintsAfterThoseOfTheParse(): void
    {
        $session = (new Instance())->connect();
        $session->query('SELECT /*+ FOO */ SQL_NO_CACHE 1');
        $operation = $session->analyze('SELECT /*+ MAX_EXECUTION_TIME(1) MAX_EXECUTION_TIME(2) */ 1');
        $hints = (new \MySqlMemory\Hint\Hints())->apply($operation->statement, $session);

        (new Execution($session))->run($operation, (new Dispatcher())->command($operation->statement), $hints);

        self::assertSame([['Warning', 3126, 'Hint MAX_EXECUTION_TIME(2) is ignored as conflicting/duplicated']], $session->diagnostics->conditions);
    }

    public function testPerformGivesTheVariablesOfSetVarTheirValuesBack(): void
    {
        $session = (new Instance())->connect();
        $operation = $session->analyze('SELECT /*+ SET_VAR(unique_checks = OFF) */ @@unique_checks');

        $reply = (new Execution($session))->perform($operation, (new Dispatcher())->command($operation->statement));

        self::assertInstanceOf(\MySqlMemory\Result\ResultSet::class, $reply);
        self::assertSame([[['0']], 'ON'], [$reply->rows, $session->variables->read('unique_checks')]);
    }
    public function testRunRefusesAWriteOfAReadOnlyTransactionBeforeItsProblems(): void
    {
        $session = (new Instance())->connect();
        $session->query('START TRANSACTION READ ONLY');
        $replies = $session->run('INSERT INTO nosuch.t VALUES (1)');
        $error = $replies[0] ?? null;

        self::assertInstanceOf(\MySqlMemory\Error\SqlError::class, $error);
        self::assertSame(1792, $error->getCode());
    }

    public function testParsingRecordsTheWarningsRaisedWhileTheStatementIsRead(): void
    {
        $session = (new Instance())->connect();
        $operation = $session->analyze("SELECT BINARY 'a'");

        (new Execution($session))->parsing($operation, $operation->facts->warnings);

        self::assertSame([['Warning', 1287, "'BINARY expr' is deprecated and will be removed in a future release. Please use CAST instead"]], $session->diagnostics->conditions);
    }

    public function testParsingFailsWithTheFirstProblemFoundWhileParsing(): void
    {
        $session = (new Instance())->connect();
        $operation = $session->analyze("SELECT ('a' COLLATE nope) COLLATE nope2");

        $this->expectException(\MySqlMemory\Error\SqlError::class);
        $this->expectExceptionCode(1273);
        $this->expectExceptionMessage("Unknown collation: 'nope'");

        (new Execution($session))->parsing($operation, $operation->facts->warnings);
    }

    public function testRetainsKeepsTheDiagnosticsOfAStatementWithoutTablesIn56(): void
    {
        $legacy = (new Instance('5.6.51'))->connect();
        $legacy->query("SELECT 'abc' + 0");
        $legacy->query('SELECT 1');
        $modern = (new Instance('5.7.44'))->connect();

        self::assertSame([['Warning', 1292, "Truncated incorrect DOUBLE value: 'abc'"]], $legacy->diagnostics->conditions);
        self::assertTrue((new Execution($legacy))->retains($legacy->analyze('SELECT 1')->statement, new \MySqlMemory\Command\QueryCommand()));
        self::assertFalse((new Execution($modern))->retains($modern->analyze('SELECT 1')->statement, new \MySqlMemory\Command\QueryCommand()));
    }

    public function testRetainsKeepsTheDiagnosticsAcrossTheTransactionStatementsOf56(): void
    {
        $session = (new Instance('5.6.51'))->connect();
        $session->run('SELECT * FROM nosuch.t');
        $session->query('SET TRANSACTION READ WRITE; BEGIN; SAVEPOINT a; COMMIT');
        $warnings = $session->query('SHOW WARNINGS')[0];

        self::assertInstanceOf(\MySqlMemory\Result\ResultSet::class, $warnings);
        self::assertSame([['Error', '1146', "Table 'nosuch.t' doesn't exist"]], $warnings->rows);
    }
}
