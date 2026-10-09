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

    public function testRetainsKeepsTheDiagnosticsAcrossTheStatementsThatOpenNoTableIn56(): void
    {
        $session = (new Instance('5.6.51'))->connect();
        $session->query('DROP DATABASE IF EXISTS nosuch');
        $session->query('CREATE DATABASE d; USE d; CREATE USER u; GRANT SELECT ON *.* TO u; SHOW GRANTS FOR u; FLUSH PRIVILEGES; SELECT @@sql_mode');
        $kept = $session->query('SHOW WARNINGS')[0];
        $session->query('CREATE TABLE t (a INT)');
        $cleared = $session->query('SHOW WARNINGS')[0];

        self::assertInstanceOf(\MySqlMemory\Result\ResultSet::class, $kept);
        self::assertInstanceOf(\MySqlMemory\Result\ResultSet::class, $cleared);
        self::assertSame([['Note', '1008', "Can't drop database 'nosuch'; database doesn't exist"]], $kept->rows);
        self::assertSame([], $cleared->rows);
    }

    public function testRunKeepsMaxErrorCountConditionsAndCountsTheRest(): void
    {
        $session = (new Instance())->connect();
        $session->query('SET max_error_count = 2');
        $session->query("SELECT 'a' + 0, 'b' + 0, 'c' + 0");
        $kept = count($session->diagnostics->conditions);
        $count = $session->query('SHOW COUNT(*) WARNINGS')[0];
        $read = $session->query('SELECT @@warning_count')[0];

        self::assertInstanceOf(\MySqlMemory\Result\ResultSet::class, $count);
        self::assertInstanceOf(\MySqlMemory\Result\ResultSet::class, $read);
        self::assertSame([2, [['3']], [['3']]], [$kept, $count->rows, $read->rows]);
    }

    public function testUndeclaredStopsTheWarningAboutIntoBeforeTheLockingClauses(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (a INT)');

        self::assertTrue((new Execution($session))->undeclared($session->analyze('SELECT a FROM t INTO nosuch FOR UPDATE')));
        self::assertFalse((new Execution($session))->undeclared($session->analyze('SELECT a FROM t INTO @a FOR UPDATE')));
    }

    public function testRunEmptiesTheAreaBeforeAGetDiagnosticsThatNamesAnUndeclaredVariable(): void
    {
        $session = (new Instance())->connect();
        $session->query('SELECT 1 / 0');
        $session->run('GET DIAGNOSTICS v = NUMBER');

        self::assertSame([['Error', 1327, 'Undeclared variable: v']], $session->diagnostics->conditions);
    }

    public function testAreaEmptiesTheAreaAndRemembersTheConditionsBefore(): void
    {
        $session = (new Instance())->connect();
        $session->query('SELECT 1 / 0');
        $operation = $session->analyze('SELECT 1');

        (new Execution($session))->area($operation, (new Dispatcher())->command($operation->statement));

        self::assertSame([[], [1, 0]], [$session->diagnostics->conditions, $session->diagnostics->previous]);
    }

    public function testAreaEmptiesTheAreaForGetDiagnosticsWithAProblemFoundWhileParsing(): void
    {
        $session = (new Instance())->connect();
        $session->run('SELECT nosuch');

        $session->run('GET DIAGNOSTICS CONDITION @@nosuch @v = MESSAGE_TEXT');

        self::assertSame([['Error', 1193, "Unknown system variable 'nosuch'"]], $session->diagnostics->conditions);
    }

    public function testFailedRecordsTheErrorAndAnswersTheRepliesBeforeIt(): void
    {
        $session = (new Instance())->connect();
        $session->variables->rowCount = 3;
        $error = \MySqlMemory\Error\Family\QueryError::NoDatabase->error();

        self::assertSame([$error], (new Execution($session))->failed($error));
        self::assertSame([-1, [['Error', 1046, 'No database selected']]], [$session->variables->rowCount, $session->diagnostics->conditions]);
    }
}
