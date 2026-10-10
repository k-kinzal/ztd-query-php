<?php

declare(strict_types=1);

namespace Tests\Unit\Program;

use MySqlMemory\Instance;
use MySqlMemory\Program\Invocation;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(Invocation::class)]
#[Small]
final class InvocationTest extends TestCase
{
    public function testRunRunsInTheDatabaseAndSqlModeOfTheProgram(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('CREATE DATABASE e');
        $session->query('USE d');
        $session->query("SET sql_mode = ''");
        $session->query('CREATE PROCEDURE p() BEGIN DECLARE v TINYINT; SET v = 300; SELECT DATABASE(), v; END');
        $session->query('SET sql_mode = DEFAULT');
        $session->query('USE e');

        $result1 = $session->query('CALL d.p()')[0];
        self::assertInstanceOf(\MySqlMemory\Result\ResultSet::class, $result1);
        self::assertSame([[['d', '127']], 'e'], [$result1->rows, $session->variables->database]);
    }

    public function testFunctionAddsTheConditionsOfItsLastStatementToTheCaller(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query("SET sql_mode = ''");
        $session->query("CREATE FUNCTION f() RETURNS INT DETERMINISTIC BEGIN DECLARE x INT; SET x = 'b' + 1; RETURN 'a' + 1; END");
        $session->query('SET sql_mode = DEFAULT');

        $session->query("SELECT 'zz' + 0, f()");

        self::assertSame([['Warning', 1292, "Truncated incorrect DOUBLE value: 'zz'"], ['Warning', 1292, "Truncated incorrect DOUBLE value: 'a'"]], $session->diagnostics->conditions);
    }

    public function testContainedLeavesRowCountAsItWas(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->variables->rowCount = 7;

        self::assertSame([5, 7], [(new Invocation($session))->contained(static fn (): int => 5), $session->variables->rowCount]);
    }

    public function testParametersStoresEachArgumentAsIntoAColumn(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE FUNCTION f(a INT, b VARCHAR(5)) RETURNS VARCHAR(10) DETERMINISTIC RETURN CONCAT(a, b)');

        $this->expectExceptionCode(1406);
        $this->expectExceptionMessage("Data too long for column 'b' at row 1");

        $session->query("SELECT f(1, 'abcdefg')");
    }

    public function testContextIsStrictUnderAStrictMode(): void
    {
        $session = (new Instance())->connect();

        self::assertSame([true, false], [(new Invocation($session))->context('STRICT_TRANS_TABLES')->strict, (new Invocation($session))->context('')->strict]);
    }
}
