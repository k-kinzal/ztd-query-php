<?php

declare(strict_types=1);

namespace Tests\Unit\Program;

use MySqlMemory\Instance;
use MySqlMemory\Program\Handlers;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(Handlers::class)]
#[Small]
final class HandlersTest extends TestCase
{
    public function testFailedRunsAnExitHandlerAndLeavesItsBlock(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (a INT PRIMARY KEY)');
        $session->query("CREATE PROCEDURE p() BEGIN DECLARE CONTINUE HANDLER FOR SQLEXCEPTION SET @w = 'outer'; BEGIN DECLARE EXIT HANDLER FOR 1062 BEGIN SET @w = 'inner'; INSERT INTO t VALUES (1), (1); END; INSERT INTO t VALUES (1), (1); SET @w = 'not here'; END; SELECT @w; END");

        $result1 = $session->query('CALL p()')[0];
        self::assertInstanceOf(\MySqlMemory\Result\ResultSet::class, $result1);
        self::assertSame([['outer']], $result1->rows);
    }

    public function testWarnedHandlesAWarningAndClearsIt(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE PROCEDURE p() BEGIN DECLARE CONTINUE HANDLER FOR SQLWARNING SET @h = 1; SET @h = 0; SELECT 1/0 INTO @x; END');

        $session->query('CALL p()');

        $result2 = $session->query('SELECT @h')[0];
        self::assertInstanceOf(\MySqlMemory\Result\ResultSet::class, $result2);
        self::assertSame([[['1']], []], [$result2->rows, $session->diagnostics->conditions]);
    }

    public function testFindPrefersTheInnermostBlock(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (a INT PRIMARY KEY)');
        $session->query("CREATE PROCEDURE p() BEGIN DECLARE CONTINUE HANDLER FOR 1062 SET @w = 'outer'; BEGIN DECLARE CONTINUE HANDLER FOR SQLEXCEPTION SET @w = 'inner'; INSERT INTO t VALUES (1), (1); END; SELECT @w; END");

        $result3 = $session->query('CALL p()')[0];
        self::assertInstanceOf(\MySqlMemory\Result\ResultSet::class, $result3);
        self::assertSame([['inner']], $result3->rows);
    }

    public function testPrecedencePrefersAnErrorNumberToAnSqlstateToAClass(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (a INT PRIMARY KEY)');
        $session->query("CREATE PROCEDURE p() BEGIN DECLARE CONTINUE HANDLER FOR SQLEXCEPTION SET @w = 'exc'; DECLARE CONTINUE HANDLER FOR SQLSTATE '23000' SET @w = 'state'; DECLARE CONTINUE HANDLER FOR 1062 SET @w = 'code'; INSERT INTO t VALUES (1), (1); SELECT @w; END");

        $result4 = $session->query('CALL p()')[0];
        self::assertInstanceOf(\MySqlMemory\Result\ResultSet::class, $result4);
        self::assertSame([['code']], $result4->rows);
    }

    public function testMeaningReadsADeclaredCondition(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (a INT PRIMARY KEY)');
        $session->query("CREATE PROCEDURE p() BEGIN DECLARE dup CONDITION FOR 1062; DECLARE EXIT HANDLER FOR dup SELECT 'duplicate'; INSERT INTO t VALUES (1), (1); END");

        $result5 = $session->query('CALL p()')[0];
        self::assertInstanceOf(\MySqlMemory\Result\ResultSet::class, $result5);
        self::assertSame([['duplicate']], $result5->rows);
    }

    public function testMatchReadsTheLevelOfACondition(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query("CREATE PROCEDURE p() BEGIN DECLARE CONTINUE HANDLER FOR SQLEXCEPTION SET @c = 'exception'; DECLARE CONTINUE HANDLER FOR NOT FOUND SET @c = 'not found'; SET @c = ''; SELECT 1 INTO @x FROM DUAL WHERE FALSE; SELECT @c; END");

        $result6 = $session->query('CALL p()')[0];
        self::assertInstanceOf(\MySqlMemory\Result\ResultSet::class, $result6);
        self::assertSame([['not found']], $result6->rows);
    }

    public function testActivateReadsTheHandledConditionsAsStackedDiagnostics(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (a INT PRIMARY KEY)');
        $session->query('INSERT INTO t VALUES (1)');
        $session->query('CREATE PROCEDURE p() BEGIN DECLARE EXIT HANDLER FOR SQLEXCEPTION BEGIN DECLARE m TEXT; DECLARE e INT; GET STACKED DIAGNOSTICS CONDITION 1 m = MESSAGE_TEXT, e = MYSQL_ERRNO; SELECT m, e; END; INSERT INTO t VALUES (1); END');

        $result7 = $session->query('CALL p()')[0];
        self::assertInstanceOf(\MySqlMemory\Result\ResultSet::class, $result7);
        self::assertSame([["Duplicate entry '1' for key 't.PRIMARY'", '1062']], $result7->rows);
    }
}
