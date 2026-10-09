<?php

declare(strict_types=1);

namespace Tests\Unit\Session;

use MySqlMemory\Error\SqlError;
use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use MySqlMemory\Session\Problems;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Call\FunctionCall;
use SqlSemantics\Platform\MySql\Statement\Query\Problem\Misuse;
use SqlSemantics\Platform\MySql\Statement\Query\Problem\MisuseRule;
use SqlSemantics\Platform\MySql\Statement\Query\With\CommonTableExpression;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(Problems::class)]
#[Small]
final class ProblemsTest extends TestCase
{
    public function testRaiseLeavesTheProblemsOfAnAlterTableToItsCommand(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d');

        $error = $session->run('CREATE INDEX i ON abc.t ((zz + 1)) ALGORITHM = bogus')[0];

        self::assertInstanceOf(SqlError::class, $error);
        self::assertSame([1800, "Unknown ALGORITHM 'bogus'"], [$error->getCode(), $error->getMessage()]);
    }

    public function testRaiseRaisesAtLocalWhileItReadsTheStatement(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d');

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1235);
        $this->expectExceptionMessage("This version of MySQL doesn't yet support 'AT LOCAL'");

        $session->query("SELECT CAST('2020-01-01' AT LOCAL AS DATETIME) FROM nope");
    }

    public function testRaiseLeavesTheProblemsOfAnAccountStatementToItsCommand(): void
    {
        $session = (new Instance())->connect();
        $operation = $session->analyze("ALTER USER nobody ATTRIBUTE '[1]'");
        (new Problems())->raise($operation, $session);

        self::assertCount(1, $operation->facts->diagnostics);
    }

    public function testRaiseLeavesAStatementWithoutDiagnostics(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect('root', 'localhost', 'd');
        $session->query('CREATE TABLE t (a INT)');
        $operation = $session->analyze('SELECT a FROM t');
        (new Problems())->raise($operation, $session);

        self::assertSame([], $operation->facts->diagnostics);
    }

    public function testRaiseRaisesAnUndeclaredVariable(): void
    {
        $session = (new Instance())->connect();

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1327);
        $this->expectExceptionMessage('Undeclared variable: x');

        $session->query('SELECT 1 INTO x');
    }

    public function testRaiseRaisesTheNameTheServerResolvesFirst(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect('root', 'localhost', 'd');
        $session->query('CREATE TABLE t (a INT)');
        $operation = $session->analyze('SELECT a FROM t WHERE y = 1 ORDER BY z');

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1054);
        $this->expectExceptionMessage("Unknown column 'y' in 'where clause'");

        (new Problems())->raise($operation, $session);
    }

    public function testRaiseRaisesTheSelectListBeforeWhere(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect('root', 'localhost', 'd');
        $session->query('CREATE TABLE t (a INT)');

        $this->expectException(SqlError::class);
        $this->expectExceptionMessage("Unknown column 'x' in 'field list'");

        $session->query('SELECT x FROM t WHERE y = 1');
    }

    public function testRaiseRaisesADerivedTableFirst(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect('root', 'localhost', 'd');
        $session->query('CREATE TABLE t (a INT)');

        $this->expectException(SqlError::class);
        $this->expectExceptionMessage("Unknown column 'q' in 'field list'");

        $session->query('SELECT x FROM (SELECT q FROM t) AS s');
    }

    public function testRaiseIgnoresNonGroupedColumnsWithoutOnlyFullGroupBy(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect('root', 'localhost', 'd');
        $session->query("CREATE TABLE t (a INT, b INT); SET sql_mode = ''");
        $result = $session->query('SELECT b, COUNT(*) FROM t')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([[null, '0']], $result->rows);
    }

    public function testRaiseRaisesTheErrorsOfTheParserFirst(): void
    {
        $session = (new Instance('8.4.7', [], ['p']))->connect('root', 'localhost', 'p');
        $session->query('CREATE TABLE w (a INT)');

        $session->run('SELECT 1 FROM nope FOR UPDATE OF p.zz');
        self::assertSame('Unresolved table name `p`.`zz` in locking clause.', $session->diagnostics->conditions[0][2]);
        $session->run('SELECT nosuch(1 AS x) FROM nope');
        self::assertSame('Incorrect parameters in the call to stored function `nosuch`', $session->diagnostics->conditions[0][2]);
        $session->run('SELECT zz, abs(1 AS x) FROM w');
        self::assertSame(1583, $session->diagnostics->conditions[0][1]);
        $session->run('SELECT * FROM w WINDOW x AS (), x AS () ORDER BY zz');
        self::assertSame("Unknown column 'zz' in 'order clause'", $session->diagnostics->conditions[0][2]);
        $session->run('SELECT ROW_NUMBER() OVER zz, yy FROM w');
        self::assertSame("Window name 'zz' is not defined.", $session->diagnostics->conditions[0][2]);
    }

    public function testRaiseRaisesAFunctionTheServerDoesNotFindWhereItResolvesTheCall(): void
    {
        $session = (new Instance('8.4.7', [], ['p']))->connect('root', 'localhost', 'p');
        $session->query('CREATE TABLE w (a INT)');
        $without = (new Instance())->connect();

        $error = $session->run('SELECT nosuch(1)')[0];
        self::assertInstanceOf(SqlError::class, $error);
        self::assertSame([1305, '42000', 'FUNCTION p.nosuch does not exist'], [$error->getCode(), $error->sqlState(), $error->getMessage()]);
        $session->run('SELECT x.NoSuch(1)');
        self::assertSame('FUNCTION x.NoSuch does not exist', $session->diagnostics->conditions[0][2]);
        $session->run('SELECT nosuch(1), zz FROM w');
        self::assertSame('FUNCTION p.nosuch does not exist', $session->diagnostics->conditions[0][2]);
        $session->run('SELECT zz FROM w WHERE nosuch(1)');
        self::assertSame("Unknown column 'zz' in 'field list'", $session->diagnostics->conditions[0][2]);
        $session->run('SELECT nosuch(1) FROM nope');
        self::assertSame("Table 'p.nope' doesn't exist", $session->diagnostics->conditions[0][2]);
        $without->run('SELECT nosuch(1)');
        self::assertSame(1046, $without->diagnostics->conditions[0][1]);
    }

    public function testRaiseLeavesTheMissingTablesOfDropTableToTheCommand(): void
    {
        $session = (new Instance('8.4.7', [], ['p']))->connect('root', 'localhost', 'p');
        $without = (new Instance())->connect();

        $session->run('DROP TABLE nope');
        self::assertSame([['Error', 1051, "Unknown table 'p.nope'"]], $session->diagnostics->conditions);
        $without->run('DROP TABLE nope');
        self::assertSame(1046, $without->diagnostics->conditions[0][1]);
    }

    public function testRaiseNamesTheClauseOfAPositionOutsideTheSelectList(): void
    {
        $session = (new Instance('8.4.7', [], ['p']))->connect('root', 'localhost', 'p');
        $session->query('CREATE TABLE w (a INT)');

        $session->run('SELECT a FROM w ORDER BY 3');
        self::assertSame([['Error', 1054, "Unknown column '3' in 'order clause'"]], $session->diagnostics->conditions);
        $session->run('SELECT a FROM w GROUP BY 3');
        self::assertSame("Unknown column '3' in 'group statement'", $session->diagnostics->conditions[0][2]);
        $session->run('SELECT a FROM w UNION SELECT a FROM w ORDER BY 3');
        self::assertSame("Unknown column '3' in 'order clause'", $session->diagnostics->conditions[0][2]);
        $session->run('SELECT a FROM w ORDER BY 3, zz');
        self::assertSame("Unknown column '3' in 'order clause'", $session->diagnostics->conditions[0][2]);
    }

    public function testRaiseIgnoresAnUnknownColumnOfACommonTableNoQueryReads(): void
    {
        $session = (new Instance('8.4.7', [], ['p']))->connect('root', 'localhost', 'p');
        $session->query('CREATE TABLE w (a INT)');

        self::assertInstanceOf(ResultSet::class, $session->query('WITH x AS (SELECT nosuch FROM w) SELECT a FROM w')[0]);
        $session->run('WITH x AS (SELECT nosuch FROM w) SELECT * FROM x');
        self::assertSame("Unknown column 'nosuch' in 'field list'", $session->diagnostics->conditions[0][2]);
    }

    public function testRaiseRaisesAJsonTablePathAfterOpeningTablesAndBeforeNames(): void
    {
        $session = (new Instance('8.4.7', [], ['p']))->connect('root', 'localhost', 'p');
        $session->query('CREATE TABLE w (a INT)');

        $session->run("UPDATE JSON_TABLE('[]', 'text' COLUMNS (q FOR ORDINALITY)) AS j SET nosuch = 1");
        self::assertSame([['Error', 3143, 'Invalid JSON path expression. The error is around character position 1.']], $session->diagnostics->conditions);
        $session->run("SELECT * FROM JSON_TABLE('[]', 'text' COLUMNS (q FOR ORDINALITY)) AS j, nosuch");
        self::assertSame(1146, $session->diagnostics->conditions[0][1]);
    }

    public function testPreparedRefusesQualifyBeforeResolvingAnyName(): void
    {
        $session = (new Instance())->connect();

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(6037);
        $this->expectExceptionMessage("'QUALIFY clause' can be used only if the hypergraph optimizer is enabled.");

        $session->query('SELECT x.* QUALIFY 1');
    }

    public function testPreparedRefusesCubeInAStatementWithoutTables(): void
    {
        $session = (new Instance())->connect();

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(6033);
        $this->expectExceptionMessage("'CUBE' is not supported");

        $session->query('SELECT * FROM DUAL GROUP BY CUBE (1)');
    }

    public function testReachedLeavesOutACommonTableNoReferenceNames(): void
    {
        $session = (new Instance())->connect();
        $statement = $session->analyze('WITH c AS (SELECT 1 QUALIFY 1), e AS (SELECT 2) SELECT * FROM e')->statement;
        $reached = (new Problems())->reached($statement);
        $result = $session->query('WITH c AS (SELECT 1 QUALIFY 1) SELECT 1')[0];

        self::assertCount(1, array_filter($reached, static fn (object $node): bool => $node instanceof CommonTableExpression));
        self::assertInstanceOf(ResultSet::class, $result);
    }

    public function testRaiseRaisesATooBigClockPrecisionWhereTheServerResolvesIt(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect('root', 'localhost', 'd');
        $session->query('CREATE TABLE t (a INT)');
        $result = $session->query('SELECT CURTIME(256), UTC_TIMESTAMP(258) FROM DUAL WHERE 0')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([[8, 0], [22, 2]], [[$result->columns[0]->length, $result->columns[0]->decimals], [$result->columns[1]->length, $result->columns[1]->decimals]]);
        $this->expectException(SqlError::class);
        $this->expectExceptionMessage("Too-big precision 7 specified for 'utc_time'. Maximum is 6.");
        $session->query('SELECT UTC_TIME(263), nosuch FROM t');
    }

    public function testRaiseFollowsAnUnknownColumnOfMatchWithTheErrorOfAgainst(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (a INT)');
        $session->run("SELECT MATCH(nope) AGAINST ('x') FROM t");
        $warnings = $session->query('SHOW WARNINGS')[0];

        self::assertInstanceOf(ResultSet::class, $warnings);
        self::assertSame([['Error', '1054', "Unknown column 'nope' in 'field list'"], ['Error', '1210', 'Incorrect arguments to AGAINST']], $warnings->rows);
    }

    public function testRaiseRefusesACastToAnArrayWhereItResolvesTheCast(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (a INT)');
        $before = $session->run('SELECT nope, CAST(1 AS SIGNED ARRAY) FROM t');
        $after = $session->run('SELECT CAST(1 AS SIGNED ARRAY), nope FROM t');

        self::assertInstanceOf(SqlError::class, $before[0]);
        self::assertSame(1054, $before[0]->getCode());
        self::assertInstanceOf(SqlError::class, $after[0]);
        self::assertSame("This version of MySQL doesn't yet support 'Use of CAST( .. AS .. ARRAY) outside of functional index in CREATE(non-SELECT)/ALTER TABLE or in general expressions'", $after[0]->getMessage());
    }

    public function testCallsAnswersTheCallsTheServerDoesNotFindInThePreparedPart(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect('root', 'localhost', 'd');
        $plain = $session->analyze('SELECT nosuch(1), abs(1)');
        $common = $session->analyze('WITH c AS (SELECT unused(1)), e AS (SELECT used(1)) SELECT * FROM e');

        self::assertSame(['nosuch'], array_map(static fn (FunctionCall $call): string => $call->name->value, (new Problems())->calls($plain)));
        self::assertSame(['used'], array_map(static fn (FunctionCall $call): string => $call->name->value, (new Problems())->calls($common)));
    }

    public function testPendingLeavesOutNonGroupedColumnsWithoutOnlyFullGroupByAndTheMissingTablesOfDropTable(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect('root', 'localhost', 'd');
        $session->query('CREATE TABLE t (a INT, b INT)');
        $grouped = $session->analyze('SELECT b, COUNT(*) FROM t');
        $dropped = $session->analyze('DROP TABLE nope');
        $strict = (new Problems())->pending($grouped, $session);
        $session->query("SET sql_mode = ''");

        self::assertCount(1, $strict);
        self::assertSame([[], []], [(new Problems())->pending($grouped, $session), (new Problems())->pending($dropped, $session)]);
    }

    public function testPathsRaisesAMissingTableBeforeTheInvalidPath(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect('root', 'localhost', 'd');
        $operation = $session->analyze("SELECT * FROM JSON_TABLE('[]', 'text' COLUMNS (q FOR ORDINALITY)) AS j, nosuch");
        (new Problems())->paths($session->analyze('SELECT 1'), $session, []);

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1146);

        (new Problems())->paths($operation, $session, $operation->facts->diagnostics);
    }

    public function testPathsRaisesTheInvalidPath(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect('root', 'localhost', 'd');
        $operation = $session->analyze("SELECT * FROM JSON_TABLE('[]', 'text' COLUMNS (q FOR ORDINALITY)) AS j");

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(3143);

        (new Problems())->paths($operation, $session, $operation->facts->diagnostics);
    }

    public function testUnlocatedRaisesAProblemBeforeTheFirstLocatedOneButAWindowDefinedTwice(): void
    {
        $session = (new Instance())->connect();
        $twice = new Misuse(MisuseRule::DuplicateWindow, new Name('w'));
        $located = new Misuse(MisuseRule::StarWithoutTables);
        (new Problems())->unlocated([$twice, $located, new Misuse(MisuseRule::DerivedWithoutAlias)], [spl_object_id($located) => [$located, ['field list', [1]]]], $session, $session->analyze('SELECT 1')->statement);

        $this->expectException(SqlError::class);
        $this->expectExceptionMessage("Window name 'v' is not defined.");

        (new Problems())->unlocated([$twice, new Misuse(MisuseRule::UnknownWindow, new Name('v'))], [], $session, $session->analyze('SELECT 1')->statement);
    }

    public function testOpenedRaisesAMissingTableOfASubqueryBeforeAnyName(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect('root', 'localhost', 'd');
        $session->query('CREATE TABLE t (a INT)');

        $subquery = $session->run('SELECT zz FROM t WHERE a IN (SELECT 1 FROM nosuch)')[0];
        $quantified = $session->run('SELECT zz = ALL (TABLE nosuch)')[0];
        $unused = $session->run('WITH c AS (SELECT * FROM nosuch) SELECT zz')[0];

        self::assertInstanceOf(SqlError::class, $subquery);
        self::assertInstanceOf(SqlError::class, $quantified);
        self::assertInstanceOf(SqlError::class, $unused);
        self::assertSame([[1146, "Table 'd.nosuch' doesn't exist"], [1146, "Table 'd.nosuch' doesn't exist"], [1054, "Unknown column 'zz' in 'field list'"]], [[$subquery->getCode(), $subquery->getMessage()], [$quantified->getCode(), $quantified->getMessage()], [$unused->getCode(), $unused->getMessage()]]);
    }

    public function testOpenedRaisesTheMissingTableAStatementWritesFirst(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect('root', 'localhost', 'd');
        $error = $session->run('INSERT INTO target SET a = (SELECT 1 FROM nosuch)')[0];

        self::assertInstanceOf(SqlError::class, $error);
        self::assertSame("Table 'd.target' doesn't exist", $error->getMessage());
    }

    public function testTestedTellsWhetherOnlyExistsReadsAStarWithoutTables(): void
    {
        $session = (new Instance())->connect();

        self::assertTrue((new Problems())->tested($session->analyze('SELECT EXISTS (SELECT *)')));
        self::assertFalse((new Problems())->tested($session->analyze('SELECT EXISTS (SELECT *), (SELECT * FROM DUAL)')));
        self::assertFalse((new Problems())->tested($session->analyze('SELECT 1')));
    }

    public function testAnalysedRefusesProcedureAnalyseInTheQueryAnInsertWrites(): void
    {
        $session = (new Instance('5.7.44', [], ['d']))->connect('root', 'localhost', 'd');
        $session->query('CREATE TABLE t (a INT)');

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1221);
        $this->expectExceptionMessage('Incorrect usage of PROCEDURE and non-SELECT');

        $session->query('INSERT INTO t SELECT * PROCEDURE ANALYSE()');
    }

    public function testRaiseRefusesResignalOutsideAHandlerBeforeItsValues(): void
    {
        $session = (new Instance())->connect();

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1645);

        $session->query('RESIGNAL SET MESSAGE_TEXT = nosuch');
    }

    public function testRaiseRefusesGetStackedDiagnosticsOutsideAHandlerBeforeItsConditionNumber(): void
    {
        $session = (new Instance())->connect();

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(3004);

        $session->query('GET STACKED DIAGNOSTICS CONDITION nosuch @a = MESSAGE_TEXT');
    }

    public function testRaiseRaisesAnUndeclaredTargetOfGetDiagnosticsAfterAnUnknownSystemVariableInMySql56(): void
    {
        $session = (new Instance('5.6.51'))->connect();

        $this->expectExceptionCode(1193);

        $session->query('GET DIAGNOSTICS CONDITION @@nosuch v = MESSAGE_TEXT');
    }

    public function testRaiseRefusesAnIndexAnUpdateNamesBeforeTheColumnsItSets(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect('root', 'localhost', 'd');
        $session->query('CREATE TABLE t (a INT)');

        $this->expectExceptionCode(1176);
        $this->expectExceptionMessage("Key 'k' doesn't exist in table 't'");

        $session->query('UPDATE t FORCE INDEX (k) SET nosuch = 1');
    }
}
