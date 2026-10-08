<?php

declare(strict_types=1);

namespace Tests\Unit\Session;

use MySqlMemory\Error\SqlError;
use MySqlMemory\Instance;
use MySqlMemory\Result\Completion;
use MySqlMemory\Result\ResultSet;
use MySqlMemory\Session\Session;
use MySqlMemory\Typing\Domain;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Coercibility;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Domain as Resolved;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field;

#[CoversClass(Session::class)]
#[Small]
final class SessionTest extends TestCase
{
    public function testQueryAnswersTheReplyOfEachStatement(): void
    {
        $session = (new Instance())->connect();
        $replies = $session->query('CREATE DATABASE shop; USE shop; CREATE TABLE items (id INT PRIMARY KEY, name VARCHAR(20)); INSERT INTO items VALUES (1, \'pen\'), (2, \'ink\'); SELECT name FROM items ORDER BY id DESC');

        self::assertCount(5, $replies);
        self::assertInstanceOf(ResultSet::class, $replies[4]);
        self::assertSame([['ink'], ['pen']], $replies[4]->rows);
    }

    public function testQueryRaisesTheFirstError(): void
    {
        $session = (new Instance())->connect();

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1046);
        $this->expectExceptionMessage('No database selected');

        $session->query('DO 1; SELECT * FROM nowhere; DO 2');
    }

    public function testRunStopsAtTheFirstError(): void
    {
        $session = (new Instance())->connect();
        $answers = $session->run('SELECT 1; SELECT * FROM nowhere; SELECT 2');

        self::assertCount(2, $answers);
        self::assertInstanceOf(ResultSet::class, $answers[0]);
        self::assertInstanceOf(SqlError::class, $answers[1]);
        self::assertSame(1046, $answers[1]->getCode());
        self::assertSame([['Error', 1046, 'No database selected']], $session->diagnostics->conditions);
    }

    public function testRunAnswersTheErrorOfAnEmptyText(): void
    {
        $session = (new Instance())->connect();
        $answers = $session->run('  ');

        self::assertCount(1, $answers);
        self::assertInstanceOf(SqlError::class, $answers[0]);
        self::assertSame(1065, $answers[0]->getCode());
        self::assertSame('Query was empty', $answers[0]->getMessage());
        self::assertSame([['Error', 1065, 'Query was empty']], $session->diagnostics->conditions);
    }

    public function testRunRestoresTheRowsOfAFailedStatement(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect('root', 'localhost', 'd');
        $session->query('CREATE TABLE t (a INT PRIMARY KEY); INSERT INTO t VALUES (3)');
        $answers = $session->run('INSERT INTO t VALUES (1), (2), (3)');
        $result = $session->query('SELECT a FROM t ORDER BY a')[0];

        self::assertInstanceOf(SqlError::class, $answers[0]);
        self::assertSame(1062, $answers[0]->getCode());
        self::assertSame("Duplicate entry '3' for key 't.PRIMARY'", $answers[0]->getMessage());
        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['3']], $result->rows);
    }

    public function testRunBindsTheValuesOfAPreparedStatement(): void
    {
        $session = (new Instance())->connect();
        $answers = $session->run('SELECT ? + 1', [[41, Domain::integer()->withNullable(false)]], true);

        self::assertInstanceOf(ResultSet::class, $answers[0]);
        self::assertSame([['42']], $answers[0]->rows);
    }

    public function testSplitAnswersEachStatementOfAText(): void
    {
        $session = (new Instance())->connect();

        self::assertSame(['SELECT 1;', ' SELECT 2'], $session->split('SELECT 1; SELECT 2'));
        self::assertSame(['SELECT 1'], $session->split('SELECT 1'));
    }

    public function testSplitRefusesAnEmptyText(): void
    {
        $session = (new Instance())->connect();

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1065);
        $this->expectExceptionMessage('Query was empty');

        $session->split("\n");
    }

    public function testSplitRefusesATextThatDoesNotParse(): void
    {
        $session = (new Instance())->connect();

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1064);
        $this->expectExceptionMessage("near 'SELEC 1' at line 1");

        $session->split('SELEC 1');
    }

    public function testExecuteAnswersTheReplyOfOneStatement(): void
    {
        $session = (new Instance())->connect();

        self::assertEquals(new Completion(1), $session->execute('CREATE DATABASE d'));
        self::assertEquals(new Completion(), $session->execute('DO 1'));
    }

    public function testExecuteClearsTheDiagnosticsOfTheLastStatement(): void
    {
        $session = (new Instance())->connect();
        $session->execute("SELECT 'x' + 1");
        $warned = $session->diagnostics->conditions;
        $session->execute('SELECT 1');

        self::assertSame([['Warning', 1292, "Truncated incorrect DOUBLE value: 'x'"]], $warned);
        self::assertSame([], $session->diagnostics->conditions);
    }

    public function testExecuteWarnsAboutIntoBeforeALockingClauseAfterReadingTheStatement(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (a INT)');
        $session->run("TABLE t INTO DUMPFILE '/tmp/out' LOCK IN SHARE MODE");
        $warned = $session->diagnostics->conditions;
        $session->run("TABLE t INTO DUMPFILE '/tmp/out' LOCK IN SHARE MODE LOCK IN SHARE MODE");

        self::assertSame([3962, 1290], array_column($warned, 1));
        self::assertSame([['Error', 3569, 'Table t appears in multiple locking clauses.']], $session->diagnostics->conditions);
    }

    public function testExecuteRaisesTheProblemOfTheStatement(): void
    {
        $session = (new Instance())->connect();

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1096);

        $session->execute('SELECT *');
    }

    public function testAnalyzeResolvesTheStatement(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect('root', 'localhost', 'd');
        $session->query('CREATE TABLE t (a INT)');
        $operation = $session->analyze('SELECT a FROM t');

        self::assertInstanceOf(Select::class, $operation->statement);
        self::assertSame([], $operation->facts->diagnostics);
    }

    public function testAnalyzeRefusesAParameterMarkerOutsideAPreparedStatement(): void
    {
        $session = (new Instance())->connect();

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1064);
        $this->expectExceptionMessage("near '?' at line 1");

        $session->analyze('SELECT ?');
    }

    public function testAnalyzeAcceptsAParameterMarkerOfAPreparedStatement(): void
    {
        $session = (new Instance())->connect();
        $operation = $session->analyze('SELECT ?', true);

        self::assertInstanceOf(Select::class, $operation->statement);
    }

    public function testAnalyzeRefusesAStatementThatDoesNotParse(): void
    {
        $session = (new Instance())->connect();

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1064);
        $this->expectExceptionMessage("near 'FROM' at line 1");

        $session->analyze('SELECT FROM');
    }

    public function testBoundTypesAMarkerOfAPreparedStatementAsAVarchar(): void
    {
        $session = (new Instance())->connect();
        $tree = $session->semantics()->parser()->parse('SELECT ? + ?');
        $unbound = Resolved::string(16383, Collation::known('utf8mb4_0900_ai_ci'), Field::VarString, Coercibility::Coercible);

        self::assertEquals([0 => $unbound, 1 => $unbound], $session->bound($tree, [], true));
    }

    public function testBoundTypesAMarkerByItsValue(): void
    {
        $session = (new Instance())->connect();
        $tree = $session->semantics()->parser()->parse('SELECT ? + ?');

        self::assertEquals([0 => Domain::integer()->resolved()], $session->bound($tree, [[1, Domain::integer()]]));
        self::assertSame([], $session->bound($tree, []));
    }

    public function testResolutionAnswersTheSessionSettings(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect();
        $session->query("SET div_precision_increment = 6, group_concat_max_len = 2048, sql_mode = 'NO_UNSIGNED_SUBTRACTION'");
        $resolution = $session->resolution();

        self::assertSame('utf8mb4_0900_ai_ci', $resolution->connection->name);
        self::assertSame('utf8mb4_0900_ai_ci', $resolution->server?->name);
        self::assertSame(6, $resolution->divPrecisionIncrement);
        self::assertSame(2048, $resolution->groupConcatMaxLen);
        self::assertFalse($resolution->unsignedSubtraction);
        self::assertSame(['information_schema', 'mysql', 'performance_schema', 'sys', 'd'], array_keys($resolution->schemas));
        self::assertSame([], $resolution->parameters);
    }

    public function testResolutionNamesTheCharacterSetStatementsAreReadIn(): void
    {
        $session = (new Instance())->connect();
        $session->query('SET character_set_client = latin1');
        $latin1 = $session->resolution()->client?->name;
        $session->query('SET character_set_client = utf8mb4');
        $result = $session->query("SELECT UPPER('straße'), CONCAT('😀', 'a')")[0];

        self::assertSame('latin1', $latin1);
        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame(["UPPER('straße')", "CONCAT('?', 'a')"], [$result->columns[0]->name, $result->columns[1]->name]);
    }

    public function testResolutionTypesTheUserVariables(): void
    {
        $session = (new Instance())->connect();
        $session->query('SET @v = 5');
        $resolution = $session->resolution([0 => Domain::integer()->resolved()]);

        self::assertEquals(['v' => Domain::integer()->withNullable(false)->resolved()], $resolution->userVariables);
        self::assertEquals([0 => Domain::integer()->resolved()], $resolution->parameters);
    }

    public function testSemanticsAnswersOneAnalyzerForEachMode(): void
    {
        $session = (new Instance())->connect();
        $first = $session->semantics();
        $again = $session->semantics();
        $session->query("SET sql_mode = 'ANSI_QUOTES'");

        self::assertSame($first, $again);
        self::assertNotSame($first, $session->semantics());
    }

    public function testSemanticsParsesUnderTheSqlMode(): void
    {
        $session = (new Instance())->connect();
        $session->query("SET sql_mode = 'ANSI_QUOTES'");

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1054);
        $this->expectExceptionMessage("Unknown column 'a' in 'field list'");

        $session->query('SELECT "a"');
    }

    public function testModesAnswersTheSqlModeOfTheSession(): void
    {
        $session = (new Instance())->connect();
        $default = $session->modes()->toString();
        $session->query("SET sql_mode = 'ANSI'");

        self::assertSame('ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION', $default);
        self::assertTrue($session->modes()->has('PIPES_AS_CONCAT'));
        self::assertFalse($session->modes()->strict());
    }

    public function testSettingsAnswersTheSettingsStatementsRunUnder(): void
    {
        $session = (new Instance('8.0.44', [], ['d']))->connect('root', 'localhost', 'd');
        $settings = $session->settings();

        self::assertSame('d', $settings->database);
        self::assertSame('8.0.44', $settings->version);
        self::assertSame(4, $settings->divPrecisionIncrement);
        self::assertSame('utf8mb4_0900_ai_ci', $settings->connectionCollation->name);
        self::assertTrue($settings->modes->has('ONLY_FULL_GROUP_BY'));
    }

    public function testQueryReadsTheDefaultRolesOfTheAccountActive(): void
    {
        $instance = new Instance();
        $instance->connect()->query('CREATE ROLE r; CREATE USER u DEFAULT ROLE r');

        $roles = $instance->connect('u')->query('SELECT CURRENT_ROLE()')[0];

        self::assertInstanceOf(ResultSet::class, $roles);
        self::assertSame([['`r`@`%`']], $roles->rows);
    }

    public function testUseMakesADatabaseTheCurrentOne(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect();
        $session->use('d');
        $result = $session->query('SELECT DATABASE()')[0];

        self::assertSame('d', $session->variables->database);
        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['d']], $result->rows);
    }

    public function testUseRefusesAnUnknownDatabase(): void
    {
        $session = (new Instance())->connect();

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1049);
        $this->expectExceptionMessage("Unknown database 'shop'");

        $session->use('shop');
    }

    public function testExecuteResetsTheDiagnosticsAreaWhenTheStatementIsNotAnalyzed(): void
    {
        $session = (new Instance('8.4.7', [], ['p']))->connect('root', 'localhost', 'p');
        $session->run('SELECT 1 FROM nope');
        $session->run('SELECT * FROM');

        self::assertSame([1064], array_map(static fn (array $condition): int => $condition[1], $session->diagnostics->conditions));
    }

    public function testExecuteSetsTheRowCountOfEachStatement(): void
    {
        $session = (new Instance())->connect();
        $start = $session->query('SELECT ROW_COUNT()')[0];
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (a INT); INSERT INTO t VALUES (1), (2), (3)');
        $inserted = $session->query('SELECT ROW_COUNT()')[0];
        $selected = $session->query('SELECT ROW_COUNT()')[0];
        $session->query('UPDATE t SET a = a + 1 WHERE a > 1');
        $session->query('SET @x = 1');
        $set = $session->query('SELECT ROW_COUNT()')[0];
        $session->run('SELECT * FROM nosuch');
        $failed = $session->query('SELECT ROW_COUNT()')[0];
        $session->query('CREATE DATABASE e');
        $created = $session->query('SELECT ROW_COUNT()')[0];

        self::assertInstanceOf(ResultSet::class, $start);
        self::assertInstanceOf(ResultSet::class, $inserted);
        self::assertInstanceOf(ResultSet::class, $selected);
        self::assertInstanceOf(ResultSet::class, $set);
        self::assertInstanceOf(ResultSet::class, $failed);
        self::assertInstanceOf(ResultSet::class, $created);
        self::assertSame([[['0']], [['3']], [['-1']], [['0']], [['-1']], [['1']]], [$start->rows, $inserted->rows, $selected->rows, $set->rows, $failed->rows, $created->rows]);
    }

    public function testRunSetsTheRowCountOfAStatementThatDoesNotParse(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->run('SELEC 1');

        self::assertSame(-1, $session->variables->rowCount);
    }

    public function testExecuteRecordsTheErrorsFoundWhileParsingAmongTheWarnings(): void
    {
        $session = (new Instance())->connect();
        $collation = $session->run("SELECT BINARY ('a' COLLATE nope) COLLATE nope2");
        $collations = $session->query('SHOW WARNINGS')[0];
        $session->run('SELECT BINARY CAST(1 AS JSON ARRAY)');
        $array = $session->query('SHOW WARNINGS')[0];

        self::assertInstanceOf(SqlError::class, $collation[0]);
        self::assertSame("Unknown collation: 'nope'", $collation[0]->getMessage());
        self::assertInstanceOf(ResultSet::class, $collations);
        self::assertSame([['Error', '1273', "Unknown collation: 'nope'"], ['Error', '1273', "Unknown collation: 'nope2'"], ['Warning', '1287', "'BINARY expr' is deprecated and will be removed in a future release. Please use CAST instead"]], $collations->rows);
        self::assertInstanceOf(ResultSet::class, $array);
        self::assertSame([['Error', '1235', "This version of MySQL doesn't yet support 'CAST-ing data to array of JSON'"]], $array->rows);
    }

    public function testModesReadTheModesOfTheRelease(): void
    {
        $session = (new Instance('5.7.44'))->connect();
        $session->query("SET sql_mode = 'TRADITIONAL'");

        self::assertTrue($session->modes()->has('NO_AUTO_CREATE_USER'));
        self::assertTrue($session->modes()->strict());
    }

    public function testCloseReleasesTheLocksOfTheSession(): void
    {
        $instance = new Instance();
        $session = $instance->connect();
        $session->query("SELECT GET_LOCK('a', 0)");
        $connected = $instance->registry->threads->connected;
        $session->close();

        self::assertSame([[1 => true], [], []], [$connected, $instance->registry->threads->connected, $instance->registry->threads->locks]);
    }

    public function testCloseRunsOnceNothingRefersToTheSession(): void
    {
        $instance = new Instance();
        $session = $instance->connect();
        $session->query("SELECT GET_LOCK('a', 0)");
        $session = null;

        self::assertSame([[], []], [$instance->registry->threads->connected, $instance->registry->threads->locks]);
    }

    public function testPerformRefusesChangingInformationSchema(): void
    {
        $s = (new Instance())->connect();

        $this->expectExceptionMessage("Access denied for user 'root'@'%' to database 'information_schema'");
        $s->query('CREATE VIEW information_schema.v AS SELECT 1');
    }

    public function testUseNamesInformationSchemaInAnyCase(): void
    {
        $s = (new Instance())->connect();
        $s->query('USE INFORMATION_SCHEMA');

        $read1 = $s->query('SELECT DATABASE()')[0];
        self::assertInstanceOf(ResultSet::class, $read1);
        self::assertSame([['information_schema']], $read1->rows);
    }
    public function testCloseRollsBackTheOpenTransactionAndReleasesItsLocks(): void
    {
        $instance = new Instance('8.4.7', [], ['d']);
        $session = $instance->connect('root', 'localhost', 'd');
        $other = $instance->connect('root', 'localhost', 'd');
        $session->query('CREATE TABLE t (a INT); INSERT INTO t VALUES (1); BEGIN; UPDATE t SET a = 2');
        $session->close();
        $other->query('UPDATE t SET a = a + 10');
        $result = $other->query('SELECT a FROM t')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['11']], $result->rows);
    }
}
