<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Compile\Family;

use MySqlMemory\Error\SqlError;
use MySqlMemory\Evaluation\Compile\Family\Calls;
use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(Calls::class)]
#[Small]
final class CallsTest extends TestCase
{
    public function testFunctionCompilesACallWrittenByName(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query("SELECT ABS(-3), CONCAT('a', 'b', 'c'), LENGTH('abc')")[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['3', 'abc', '3']], $result->rows);
    }

    public function testFunctionRefusesAStoredFunctionThatDoesNotExist(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1305);
        $this->expectExceptionMessage('FUNCTION d.f does not exist');

        $session->query('SELECT d.f(1)');
    }

    public function testKeywordCompilesACallOfAFunctionWhoseNameIsAKeyword(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query("SELECT LEFT('abcd', 2), RIGHT('abcd', 2), REPEAT('ab', 2), INSERT('abcd', 2, 1, 'X')")[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['ab', 'cd', 'abab', 'aXcd']], $result->rows);
    }

    public function testKeywordRefusesGroupingOutsideABlockWithRollup(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (a INT)');

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1111);
        $this->expectExceptionMessage('Invalid use of group function');

        $session->query('SELECT a, GROUPING(a) FROM t GROUP BY a');
    }

    public function testNamedRefusesAWrongNumberOfArguments(): void
    {
        $session = (new Instance())->connect();

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1582);
        $this->expectExceptionMessage("Incorrect parameter count in the call to native function 'ABS'");

        $session->query('SELECT ABS(1, 2)');
    }

    public function testNamedCompilesTheArgumentsOfTheCall(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query('SELECT ABS(2 - 5), ABS(NULL)')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['3', null]], $result->rows);
    }

    public function testClockCompilesTheClocksWithTheirFractionalDigits(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query('SELECT LENGTH(CURDATE()), LENGTH(NOW()), LENGTH(NOW(3)), LENGTH(CURTIME()), LENGTH(UTC_TIMESTAMP(6))')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['10', '19', '23', '8', '26']], $result->rows);
    }

    public function testClockReadsTheSameInstantForEveryCallOfAStatement(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query('SELECT NOW(6) = NOW(6), CURRENT_TIMESTAMP(6) = NOW(6)')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['1', '1']], $result->rows);
    }

    public function testNamedPrintsTheCallForMessages(): void
    {
        $session = (new Instance())->connect();

        $this->expectException(SqlError::class);
        $this->expectExceptionMessage("DOUBLE value is out of range in 'cot(-(0e0))'");

        $session->query('SELECT COT(-0e0)');
    }

    public function testNamedCompilesIsnullAsATestOfNull(): void
    {
        $session = (new Instance())->connect();
        $session->query("CREATE DATABASE d; USE d; CREATE TABLE t (s CHAR(2)); INSERT INTO t VALUES ('a'), (NULL)");
        $result = $session->query("SELECT ISNULL(s < 0), ISNULL('a' + 0), ISNULL(s) FROM t")[0];
        $warnings = $session->query('SHOW WARNINGS')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['0', '0', '0'], ['1', '0', '1']], $result->rows);
        self::assertInstanceOf(ResultSet::class, $warnings);
        self::assertSame([], $warnings->rows);
    }

    public function testNamedCountsArgumentsKnownForTheStatementAsKnownWhenTheRoutineAsks(): void
    {
        $session = (new Instance())->connect();
        $session->query('SET @s = 1');
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (a INT)');
        $session->query('INSERT INTO t VALUES (1), (2)');
        $result = $session->query('SELECT RAND(@s) FROM t')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['0.40540353712197724'], ['0.8716141803857071']], $result->rows);
    }

    public function testNamedLetsTheRoutineCheckArgumentsKnownWhenTheStatementIsResolved(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (a VARCHAR(10))');
        $result = $session->query('SELECT SHA2(a, 7) FROM t')[0];
        $warnings = $session->query('SHOW WARNINGS')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertInstanceOf(ResultSet::class, $warnings);
        self::assertSame([[], [['Warning', '1583', "Incorrect parameters in the call to native function 'sha2'"]]], [$result->rows, $warnings->rows]);
    }

    public function testStoredCallsAStoredFunctionOfADatabaseNamedInAnotherCase(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE FUNCTION f() RETURNS TINYINT UNSIGNED DETERMINISTIC RETURN 1');

        $result1 = $session->query('SELECT d.f(), D.F()')[0];
        self::assertInstanceOf(ResultSet::class, $result1);
        self::assertSame([['1', '1']], $result1->rows);
    }

    public function testStoredRefusesAnotherNumberOfArguments(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE FUNCTION f(a INT, b INT) RETURNS INT DETERMINISTIC RETURN a + b');

        $this->expectExceptionCode(1318);
        $this->expectExceptionMessage('Incorrect number of arguments for FUNCTION d.f; expected 2, got 1');

        $session->query('SELECT f(1)');
    }

    public function testNamedReadsAConstantStringOnceOnlyWhereAnotherArgumentSettlesTheCharacterSet(): void
    {
        $session = (new Instance())->connect();
        $session->query("SELECT CONVERT(X'4142FF4344' USING utf8mb4), HEX(CAST(X'41FF' AS CHAR CHARACTER SET ascii))");
        $first = $session->query('SHOW WARNINGS')[0];
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (a INT)');
        $session->query("SELECT CONCAT('x' - INTERVAL 1 DAY, USER()) FROM t");
        $second = $session->query('SHOW WARNINGS')[0];

        self::assertInstanceOf(ResultSet::class, $first);
        self::assertInstanceOf(ResultSet::class, $second);
        self::assertSame([[['Warning', '1300', "Invalid utf8mb4 character string: 'FF4344'"], ['Warning', '1300', "Invalid ascii character string: 'FF'"]], [['Warning', '1292', "Incorrect datetime value: 'x'"]]], [$first->rows, $second->rows]);
    }
}
