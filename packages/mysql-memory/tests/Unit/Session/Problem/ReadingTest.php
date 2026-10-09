<?php

declare(strict_types=1);

namespace Tests\Unit\Session\Problem;

use MySqlMemory\Error\SqlError;
use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use MySqlMemory\Session\Problem\Reading;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Query\Select;

#[CoversClass(Reading::class)]
#[Small]
final class ReadingTest extends TestCase
{
    public function testVariablesRefusesAnUndeclaredWindowCountBeforeTheFrame(): void
    {
        $session = (new Instance())->connect();

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1327);
        $this->expectExceptionMessage('Undeclared variable: missing');

        $session->query('SELECT NTILE(missing) OVER (RANGE CURRENT ROW EXCLUDE CURRENT ROW)');
    }

    public function testReadRaisesAnUnknownCollationAndAnUnknownTableOfAMultipleTableDeleteBeforeOpeningTables(): void
    {
        $session = (new Instance('8.4.7', [], ['p']))->connect('root', 'localhost', 'p');
        $session->query('CREATE TABLE w (a INT, b INT)');

        $session->run("SELECT nosuch FROM nosuch WHERE 'a' COLLATE zz");
        self::assertSame([['Error', 1273, "Unknown collation: 'zz'"]], $session->diagnostics->conditions);
        $session->run('DELETE x.* FROM w PARTITION (p0), nosuch');
        self::assertSame([['Error', 1109, "Unknown table 'x' in MULTI DELETE"]], $session->diagnostics->conditions);
    }

    public function testReadRaisesATableLockedTwice(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (a INT)');

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(3569);
        $this->expectExceptionMessage('Table t appears in multiple locking clauses.');

        $session->query('TABLE t LOCK IN SHARE MODE LOCK IN SHARE MODE');
    }

    public function testReadRaisesALockingClauseBeforeAnIntoVariable(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (a INT)');

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(3568);
        $this->expectExceptionMessage('Unresolved table name `u` in locking clause.');

        $session->query('SELECT * FROM t FOR SHARE OF u INTO v');
    }

    public function testReadRaisesATableAliasUsedTwiceBeforeTheLockingClauses(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (a INT)');

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1066);
        $this->expectExceptionMessage("Not unique table/alias: 't'");

        $session->query('SELECT * FROM t, t FOR SHARE OF u');
    }

    public function testReadRaisesAnUndeclaredVariableOfLimitOnlyWhereTheCommonTableIsUsed(): void
    {
        $session = (new Instance())->connect();

        $reply = $session->query('WITH c AS (SELECT 1 LIMIT n) SELECT 1')[0];

        self::assertInstanceOf(ResultSet::class, $reply);
        self::assertSame([['1']], $reply->rows);
        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1327);

        $session->query('WITH c AS (SELECT 1 LIMIT n) SELECT * FROM c');
    }

    public function testRepeatedFindsACommonTableDefinedTwiceInsideAnUnusedOne(): void
    {
        $session = (new Instance())->connect();

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1066);
        $this->expectExceptionMessage("Not unique table/alias: 'c'");

        $session->query('WITH d AS (WITH c AS (SELECT 1), c AS (SELECT 2) SELECT 1) SELECT 1');
    }

    public function testReadRaisesATooBigCastPrecisionBeforeOpeningAnyTable(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect('root', 'localhost', 'd');

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1426);
        $this->expectExceptionMessage("Too-big precision 263 specified for 'CAST'. Maximum is 6.");
        $session->query('SELECT CAST(NOW() AS TIME(263)) FROM nosuch');
    }

    public function testReadRefusesTheTooBigPrecisionOfAtTimeZoneFirst(): void
    {
        $session = (new Instance())->connect();
        $answers = $session->run("SELECT CAST(nope AT TIME ZONE 'x' AS DATETIME(7))");

        self::assertInstanceOf(SqlError::class, $answers[0]);
        self::assertSame("Too-big precision 7 specified for 'CAST'. Maximum is 6.", $answers[0]->getMessage());
    }

    public function testIntoRefusesAVariableOfAStoredProgram(): void
    {
        $session = (new Instance())->connect();
        (new Reading())->into($session->analyze('SELECT 1 INTO @x')->statement);

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1327);
        $this->expectExceptionMessage('Undeclared variable: x');

        (new Reading())->into($session->analyze('SELECT 1 INTO x')->statement);
    }

    public function testReadRaisesAnUnknownSystemVariableBeforeOpeningTablesAndAVariableOfAViewBeforeIt(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect('root', 'localhost', 'd');

        $variable = $session->run('SELECT * FROM nosuch WHERE @@nosuchvar')[0];
        $view = $session->run('CREATE VIEW v AS SELECT @@nosuchvar')[0];
        $cast = $session->run('SELECT ABS(1, 2), CAST(1 AS TIME(9))')[0];

        self::assertInstanceOf(SqlError::class, $variable);
        self::assertInstanceOf(SqlError::class, $view);
        self::assertInstanceOf(SqlError::class, $cast);
        self::assertSame([[1193, "Unknown system variable 'nosuchvar'"], [1351, "View's SELECT contains a variable or parameter"], [1426, "Too-big precision 9 specified for 'CAST'. Maximum is 6."]], [[$variable->getCode(), $variable->getMessage()], [$view->getCode(), $view->getMessage()], [$cast->getCode(), $cast->getMessage()]]);
    }

    public function testTargetsRefusesATableAMultipleTableDeleteNamesTwice(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect('root', 'localhost', 'd');
        $session->query('CREATE TABLE t (a INT); INSERT INTO t VALUES (1)');
        $twice = $session->run('DELETE d.t, t FROM t')[0];
        $others = $session->run('DELETE a.t, b.t FROM t')[0];

        self::assertInstanceOf(SqlError::class, $twice);
        self::assertInstanceOf(SqlError::class, $others);
        self::assertSame([[1066, "Not unique table/alias: 't'"], [1109, "Unknown table 't' in MULTI DELETE"]], [[$twice->getCode(), $twice->getMessage()], [$others->getCode(), $others->getMessage()]]);
    }

    public function testLegacyWritesRefusesANonUpdatableTargetFirstIn57(): void
    {
        $session = (new Instance('5.7.44'))->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1288);

        (new Reading())->legacyWrites($session->analyze('UPDATE (SELECT *) AS x SET a = 1'), $session);
    }

    public function testReadRefusesAnUndeclaredIntoVariableBeforeTheUnionInMySql57(): void
    {
        $session = (new Instance('5.7.44', [], ['d']))->connect('root', 'localhost', 'd');

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1327);
        $this->expectExceptionMessage('Undeclared variable: v');

        $session->query('(SELECT 1 INTO v) UNION SELECT 2');
    }

    public function testReadRefusesWithCubeWhileParsingInMySql57(): void
    {
        $session = (new Instance('5.7.44', [], ['d']))->connect('root', 'localhost', 'd');

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1235);
        $this->expectExceptionMessage("This version of MySQL doesn't yet support 'CUBE'");

        $session->query('SELECT a FROM nosuch GROUP BY a WITH CUBE');
    }

    public function testReadRefusesDistinctWithRollupWhileParsingInMySql56(): void
    {
        $session = (new Instance('5.6.51', [], ['d']))->connect('root', 'localhost', 'd');

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1221);
        $this->expectExceptionMessage('Incorrect usage of WITH ROLLUP and DISTINCT');

        $session->query('SELECT DISTINCT a FROM nosuch GROUP BY a WITH ROLLUP ORDER BY a');
    }

    public function testReadRefusesAQueryCacheModifierBeforeANonUpdatableTargetInMySql56(): void
    {
        $session = (new Instance('5.6.51', [], ['d']))->connect('root', 'localhost', 'd');
        $session->query('CREATE TABLE t (a INT)');

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1234);

        $session->query('UPDATE (SELECT SQL_CACHE * FROM t) AS n SET a = 1');
    }

    public function testReadRefusesAKeyPartOfLengthZeroBeforeTheTable(): void
    {
        $session = (new Instance())->connect();

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1391);
        $this->expectExceptionMessage("Key part 'b' length cannot be 0");

        $session->query('CREATE INDEX i ON nosuch.t (a(1), b(0))');
    }

    public function testIntoRefusesAnUndeclaredTargetOfGetDiagnosticsBeforeTheConditionNumber(): void
    {
        $session = (new Instance())->connect();

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1327);

        $session->query('GET DIAGNOSTICS CONDITION nosuch v = CLASS_ORIGIN');
    }

    public function testLimitedRefusesAnUndeclaredLimitVariableBeforeTheBlocksAfterInMySql57(): void
    {
        $session = (new Instance('5.7.44'))->connect();

        $this->expectException(SqlError::class);
        $this->expectExceptionMessage('Undeclared variable: a');

        $session->query('(SELECT 1 LIMIT a) UNION (SELECT SQL_CACHE 2)');
    }

    public function testParsedRaisesAnUnknownCollationFirst(): void
    {
        $session = (new Instance())->connect();

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1273);
        $this->expectExceptionMessage("Unknown collation: 'zz'");

        (new Reading())->parsed($session->analyze("SELECT nosuch FROM nosuch WHERE 'a' COLLATE zz"), $session);
    }

    public function testGroupingRefusesWithCubeInMySql57(): void
    {
        $session = (new Instance('5.7.44'))->connect();
        (new Reading())->grouping($session->analyze('SELECT 1 FROM DUAL GROUP BY 1 WITH ROLLUP'), $session);

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1235);

        (new Reading())->grouping($session->analyze('SELECT 1 FROM DUAL GROUP BY 1 WITH CUBE'), $session);
    }

    public function testVariablesRefusesAnUndeclaredIntoVariable(): void
    {
        $session = (new Instance())->connect();
        (new Reading())->variables($session->analyze('SELECT 1 INTO @v'), $session);

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1327);
        $this->expectExceptionMessage('Undeclared variable: v');

        (new Reading())->variables($session->analyze('SELECT 1 INTO v'), $session);
    }

    public function testDiagnosedRaisesATableAMultipleTableDeleteLacks(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect('root', 'localhost', 'd');
        $session->query('CREATE TABLE w (a INT)');

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1109);
        $this->expectExceptionMessage("Unknown table 'x' in MULTI DELETE");

        (new Reading())->diagnosed($session->analyze('DELETE x.* FROM w'), $session);
    }

    public function testDeclaredRaisesTheLimitOfABlockWhenItEnds(): void
    {
        $session = (new Instance())->connect();
        $operation = $session->analyze('SELECT 1 LIMIT v');
        $select = (new \MySqlMemory\Evaluation\Compile\Walker())->find($operation->statement, Select::class)[0];
        (new Reading())->declared($select, false, $session, $operation);

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1327);
        $this->expectExceptionMessage('Undeclared variable: v');

        (new Reading())->declared($select, true, $session, $operation);
    }

    public function testDeclaredRaisesAnIntoAfterTheItemsWhenTheBlockBegins(): void
    {
        $session = (new Instance())->connect();
        $operation = $session->analyze('SELECT 1 INTO v FROM DUAL LIMIT 1');
        $select = (new \MySqlMemory\Evaluation\Compile\Walker())->find($operation->statement, Select::class)[0];
        (new Reading())->declared($select, true, $session, $operation);

        $this->expectExceptionMessage('Undeclared variable: v');

        (new Reading())->declared($select, false, $session, $operation);
    }

    public function testDeclaredRaisesTheLimitOffsetFirstInEveryRelease(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect('root', 'localhost', 'd');
        $session->query('CREATE TABLE t (a INT)');

        $this->expectExceptionMessage('Undeclared variable: a');

        $session->query('SELECT * FROM t LIMIT a, b');
    }

    public function testDeclaredRaisesAnIntoBeforeTheLimitWrittenAfterIt(): void
    {
        $session = (new Instance('8.0.44'))->connect();

        $this->expectExceptionMessage('Undeclared variable: v');

        $session->query('SELECT 1 INTO v FROM DUAL LIMIT a');
    }

    public function testDeclaredRaisesTheLimitOfASubqueryBeforeThatOfItsBlock(): void
    {
        $session = (new Instance('9.1.0'))->connect();

        $this->expectExceptionMessage('Undeclared variable: a');

        $session->query('SELECT (SELECT 1 LIMIT a) LIMIT b');
    }

    public function testAliasedRaisesAnAliasUsedTwiceBeforeTheLimit(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect('root', 'localhost', 'd');
        $session->query('CREATE TABLE t (a INT)');

        $this->expectExceptionMessage("Not unique table/alias: 't'");

        $session->query('SELECT * FROM t, t LIMIT a');
    }

    public function testVariablesIgnoresTheLimitOfAStoredProgramBody(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect('root', 'localhost', 'd');

        $session->query('CREATE PROCEDURE p(n INT) SELECT 1 LIMIT n');
        $result = $session->query("SELECT ROUTINE_NAME FROM information_schema.ROUTINES WHERE ROUTINE_SCHEMA = 'd'")[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['p']], $result->rows);
    }
}
