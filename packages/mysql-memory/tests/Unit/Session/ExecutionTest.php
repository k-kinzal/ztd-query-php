<?php

declare(strict_types=1);

namespace Tests\Unit\Session;

use MySqlMemory\Command\Account\GrantCommand;
use MySqlMemory\Command\Dispatcher;
use MySqlMemory\Command\QueryCommand;
use MySqlMemory\Error\Family\QueryError;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Hint\Hints;
use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use MySqlMemory\Session\Execution;
use MySqlMemory\Session\State\StatementCounters;
use MySqlMemory\Session\Statement\Binding;
use MySqlMemory\Typing\Domain;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\GrantPrivileges;
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\Item\AllPrivileges;
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\Level\CurrentDatabaseLevel;
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\Level\ObjectKind;
use SqlSemantics\Platform\MySql\Statement\Account\User\Grantee;
use SqlSemantics\Platform\MySql\Statement\Expression\Operator\Concatenation;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Literal\Parameter;
use SqlSemantics\Platform\MySql\Statement\Literal\Text;
use SqlSemantics\Platform\MySql\Statement\Name\CurrentUser;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Query\SelectExpression;
use SqlSemantics\Platform\MySql\Statement\Routine\Condition\Signal;
use SqlSemantics\Platform\MySql\Statement\Routine\Condition\SqlState;
use Tests\Generator\Bindings;
use Tests\Generator\Embedding;

#[CoversClass(Execution::class)]
#[Small]
final class ExecutionTest extends TestCase
{
    public function testStatementRollsBackEarlierRowsWhenAConcreteInsertFailsConversion(): void
    {
        $session = (new Instance('8.4.7', databases: ['pbt']))->connect(database: 'pbt');
        $session->query("SET sql_mode='STRICT_ALL_TABLES'; CREATE TABLE target (amount INT)");
        $insert = $session->analyze("INSERT target VALUES ('42'), ('invalid')");
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Dml\Insert\InsertRows::class, $insert);
        $session->text = 'an execution label, not SQL';

        try {
            (new Execution($session))->statement($insert);
            self::fail('Strict integer conversion must reject the second row.');
        } catch (SqlError $error) {
            self::assertSame(1366, $error->getCode());
        }

        $table = $session->instance->dictionary->table('pbt', 'target');
        self::assertNotNull($table);
        self::assertSame([], $table->data->rows);
        self::assertSame('an execution label, not SQL', $session->text);
    }

    public function testPerformRetainsASignalWithoutReadingRequestText(): void
    {
        $session = (new Instance())->connect();
        $session->text = 'this is not SQL';
        $statement = new Signal(new SqlState(new Text('45000')));
        $operation = (new Binding())->operation($session, $statement);
        try {
            (new Execution($session))->perform($operation, (new Dispatcher())->command($statement));
            self::fail('SIGNAL must raise its declared SQL condition.');
        } catch (SqlError $error) {
            self::assertSame(1644, $error->getCode());
            self::assertSame('45000', $error->sqlState());
            self::assertSame('Unhandled user-defined exception condition', $error->getMessage());
            self::assertSame('this is not SQL', $session->text);
        }
    }

    public function testReadingChecksLegacyDatabaseBeforeDefinitionConditions(): void
    {
        $session = (new Instance('5.7.44'))->connect();
        $operation = $session->analyze('CREATE TABLE t(a INT) CHARACTER SET missing');

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1046);
        (new Execution($session))->reading($operation, true);
    }

    public function testFailedRetainsRowsProducedBeforeTheError(): void
    {
        $session = (new Instance())->connect();
        $answers = $session->run("SELECT JSON_EXTRACT(v,'$') FROM (SELECT '1' AS v UNION ALL SELECT 'invalid') AS t");

        self::assertCount(2, $answers);
        self::assertInstanceOf(ResultSet::class, $answers[0]);
        self::assertFalse($answers[0]->complete);
        self::assertSame([['1']], $answers[0]->rows);
        self::assertInstanceOf(SqlError::class, $answers[1]);
        self::assertNull($session->running->partial);
    }


    public function testPerformRunsAnAnalyzedStatementAndSetsRowCount(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (a INT)');
        $operation = $session->analyze('INSERT INTO t VALUES (1), (2)');

        (new Execution($session))->perform($operation, (new Dispatcher())->command($operation));

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
        $hints = (new Hints())->apply($operation, $session);

        (new Execution($session))->run($operation, (new Dispatcher())->command($operation), $hints);

        self::assertSame([['Warning', 3126, 'Hint MAX_EXECUTION_TIME(2) is ignored as conflicting/duplicated']], $session->diagnostics->conditions);
    }

    public function testPerformGivesTheVariablesOfSetVarTheirValuesBack(): void
    {
        $session = (new Instance())->connect();
        $operation = $session->analyze('SELECT /*+ SET_VAR(unique_checks = OFF) */ @@unique_checks');

        $reply = (new Execution($session))->perform($operation, (new Dispatcher())->command($operation));

        self::assertInstanceOf(ResultSet::class, $reply);
        self::assertSame([[['0']], 'ON'], [$reply->rows, $session->variables->read('unique_checks')]);
    }

    public function testRunRefusesAWriteOfAReadOnlyTransactionBeforeItsProblems(): void
    {
        $session = (new Instance())->connect();
        $session->query('START TRANSACTION READ ONLY');
        $replies = $session->run('INSERT INTO nosuch.t VALUES (1)');
        $error = $replies[0] ?? null;

        self::assertInstanceOf(SqlError::class, $error);
        self::assertSame(1792, $error->getCode());
    }

    public function testParsingEmitsSpatialDeprecationsAtPreparationAndBeforeSyntaxErrors(): void
    {
        $session = (new Instance('5.7.44'))->connect();
        $session->query("PREPARE p FROM 'SELECT SRID(Point(1,2))'");

        self::assertSame(1287, $session->diagnostics->conditions[0][1]);
        $session->query('EXECUTE p');
        self::assertSame([], $session->diagnostics->conditions);
        $session->run('SELECT SRID(NULL) WHERE FALSE');
        self::assertSame([1287, 1064], array_column($session->diagnostics->conditions, 1));
    }

    public function testParsingStopsAtAnAbortingDeprecation(): void
    {
        $session = (new Instance())->connect();
        $reply = $session->run('SELECT BINARY CAST(1 AT LOCAL AS SIGNED), BINARY 2')[0];

        self::assertInstanceOf(SqlError::class, $reply);
        self::assertSame([1235, 1287], array_column($session->diagnostics->conditions, 1));
    }

    public function testParsingRecordsTheWarningsRaisedWhileTheStatementIsRead(): void
    {
        $session = (new Instance())->connect();
        $operation = $session->analyze("SELECT BINARY 'a'");

        (new Execution($session))->parsing($operation, $operation->statementInformation()->warnings);

        self::assertSame([['Warning', 1287, "'BINARY expr' is deprecated and will be removed in a future release. Please use CAST instead"]], $session->diagnostics->conditions);
    }

    public function testParsingFailsWithTheFirstProblemFoundWhileParsing(): void
    {
        $session = (new Instance())->connect();
        $operation = $session->analyze("SELECT ('a' COLLATE nope) COLLATE nope2");

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1273);
        $this->expectExceptionMessage("Unknown collation: 'nope'");

        (new Execution($session))->parsing($operation, $operation->statementInformation()->warnings);
    }

    public function testRetainsKeepsTheDiagnosticsOfAStatementWithoutTablesIn56(): void
    {
        $legacy = (new Instance('5.6.51'))->connect();
        $legacy->query("SELECT 'abc' + 0");
        $legacy->query('SELECT 1');
        $modern = (new Instance('5.7.44'))->connect();

        self::assertSame([['Warning', 1292, "Truncated incorrect DOUBLE value: 'abc'"]], $legacy->diagnostics->conditions);
        $legacyQuery = $legacy->analyze('SELECT 1');
        $modernQuery = $modern->analyze('SELECT 1');
        self::assertTrue((new Execution($legacy))->retains($legacyQuery, new QueryCommand()));
        self::assertFalse((new Execution($modern))->retains($modernQuery, new QueryCommand()));
    }

    public function testRetainsKeepsTheDiagnosticsAcrossTheTransactionStatementsOf56(): void
    {
        $session = (new Instance('5.6.51'))->connect();
        $session->run('SELECT * FROM nosuch.t');
        $session->query('SET TRANSACTION READ WRITE; BEGIN; SAVEPOINT a; COMMIT');
        $warnings = $session->query('SHOW WARNINGS')[0];

        self::assertInstanceOf(ResultSet::class, $warnings);
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

        self::assertInstanceOf(ResultSet::class, $kept);
        self::assertInstanceOf(ResultSet::class, $cleared);
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

        self::assertInstanceOf(ResultSet::class, $count);
        self::assertInstanceOf(ResultSet::class, $read);
        self::assertSame([2, [['3']], [['3']]], [$kept, $count->rows, $read->rows]);
    }

    public function testUndeclaredStopsTheWarningAboutIntoBeforeTheLockingClauses(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (a INT)');

        $missing = $session->analyze('SELECT a FROM t INTO nosuch FOR UPDATE');
        $present = $session->analyze('SELECT a FROM t INTO @a FOR UPDATE');
        self::assertTrue((new Execution($session))->undeclared($missing));
        self::assertFalse((new Execution($session))->undeclared($present));
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

        (new Execution($session))->area($operation, (new Dispatcher())->command($operation));

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
        $error = QueryError::NoDatabase->error();

        self::assertSame([$error], (new Execution($session))->failed($error));
        self::assertSame([-1, [['Error', 1046, 'No database selected']]], [$session->variables->rowCount, $session->diagnostics->conditions]);
    }

    public function testRetainsClearsLegacyDiagnosticsWhenADerivedTableIsOpened(): void
    {
        $session = (new Instance('5.6.51'))->connect();
        $session->run('SELECT missing');
        $session->query('SELECT 1');

        self::assertSame(1054, $session->diagnostics->conditions[0][1]);

        $session->query('SELECT n FROM (SELECT 1 AS n) AS t');

        self::assertSame([], $session->diagnostics->conditions);
    }

    public function testRetainsIncludesTheImplicitCurrentDatabaseGrantLevel(): void
    {
        $session = (new Instance('5.6.51'))->connect();
        $grant = new GrantPrivileges([new AllPrivileges()], ObjectKind::Table, new CurrentDatabaseLevel(), [new Grantee(new CurrentUser())]);
        self::assertTrue((new Execution($session))->retains($grant, new GrantCommand()));
    }

    public function testPerformBindsValuesWithoutImposingProtocolPreparation(): void
    {
        $node = new \SqlSemantics\Platform\MySql\Statement\Replication\Log\PurgeLogsBefore(new Parameter());
        $parameters = [['2024-01-01 00:00:00', Domain::string(19, \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation::binary())]];
        $session1 = (new Instance('8.4.7'))->connect();
        $statement1 = (new Embedding())->statement($node);
        $parameters1 = $parameters;
        $session1->activity->begin();
        StatementCounters::received($session1);

        $session1->dots = [];
        $operation1 = (new Binding())->operation($session1, $statement1, $parameters1, new LanguageProfile($session1->semantics()->profile()->grammar, $session1->semantics()->profile()->lexical, (new Bindings())->parameters($statement1)));
        self::assertInstanceOf(\MySqlMemory\Result\Completion::class, (new Execution($session1))->perform($operation1, (new Dispatcher())->command($operation1), $parameters1));
    }

    public function testPerformBindsNullParametersInsideTypedParentExpressions(): void
    {
        $query = new Select([], [new SelectExpression(new \SqlSemantics\Platform\MySql\Statement\Call\Temporal\GetFormat(\SqlSemantics\Platform\MySql\Statement\Call\Temporal\TemporalFormat::Date, new Parameter(':format'))), new SelectExpression(new Concatenation(new Parameter(), new Parameter())), new SelectExpression(new \SqlSemantics\Platform\MySql\Statement\Expression\Branching\CaseExpression(null, [new \SqlSemantics\Platform\MySql\Statement\Expression\Branching\CaseBranch(new NumberLiteral('1'), new Parameter())]))]);
        $bindings = array_fill(0, (new Bindings())->count($query), [null, Domain::null()]);
        $session3 = (new Instance('8.4.7'))->connect();
        $session3->variables->session['sql_mode'] = 'PIPES_AS_CONCAT';
        $statement3 = (new Embedding())->statement($query);
        $parameters3 = $bindings;
        $session3->activity->begin();
        StatementCounters::received($session3);

        $session3->dots = [];
        $operation3 = (new Binding())->operation($session3, $statement3, $parameters3, new LanguageProfile($session3->semantics()->profile()->grammar, $session3->semantics()->profile()->lexical, (new Bindings())->parameters($statement3)));
        $reply = (new Execution($session3))->perform($operation3, (new Dispatcher())->command($operation3), $parameters3);
        self::assertCount(4, $bindings);
        self::assertInstanceOf(ResultSet::class, $reply);
        self::assertSame([[null, null, null]], $reply->rows);
    }
}
