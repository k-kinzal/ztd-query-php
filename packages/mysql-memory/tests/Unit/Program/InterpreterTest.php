<?php

declare(strict_types=1);

namespace Tests\Unit\Program;

use MySqlMemory\Instance;
use MySqlMemory\Program\Activation;
use MySqlMemory\Program\Interpreter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Block;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation;
use SqlSemantics\Platform\MySql\Statement\Utility\Set\SetVariables;

#[CoversClass(Interpreter::class)]
#[Small]
final class InterpreterTest extends TestCase
{
    public function testSequenceRunsTheStatementsOfABodyInOrder(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query("CREATE PROCEDURE p() BEGIN SET @s = 'a'; SET @s = CONCAT(@s, 'b'); SELECT @s; END");

        $result1 = $session->query('CALL p()')[0];
        self::assertInstanceOf(\MySqlMemory\Result\ResultSet::class, $result1);
        self::assertSame([['ab']], $result1->rows);
    }

    public function testStatementRunsWhileItIterates(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query("CREATE PROCEDURE p(n INT) BEGIN DECLARE i INT DEFAULT 0; DECLARE s VARCHAR(100) DEFAULT ''; l: WHILE i < n DO SET i = i + 1; IF i = 2 THEN ITERATE l; END IF; SET s = CONCAT(s, i); IF i >= 4 THEN LEAVE l; END IF; END WHILE; SELECT s, i; END");

        $result2 = $session->query('CALL p(10)')[0];
        self::assertInstanceOf(\MySqlMemory\Result\ResultSet::class, $result2);
        self::assertSame([['134', '4']], $result2->rows);
    }

    public function testNamedRaisesTheSqlstateOfADeclaredCondition(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query("CREATE PROCEDURE p() BEGIN DECLARE c CONDITION FOR SQLSTATE '45001'; SIGNAL c SET MESSAGE_TEXT = 'boom'; END");

        $this->expectExceptionCode(1644);
        $this->expectExceptionMessage('boom');

        $session->query('CALL p()');
    }

    public function testCursorHandsTheErrorOfACursorToTheHandlers(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query("CREATE PROCEDURE p() BEGIN DECLARE x INT; DECLARE c CURSOR FOR SELECT 1; DECLARE CONTINUE HANDLER FOR 1326 SET @e = 'closed'; FETCH c INTO x; SELECT @e; END");

        $result3 = $session->query('CALL p()')[0];
        self::assertInstanceOf(\MySqlMemory\Result\ResultSet::class, $result3);
        self::assertSame([['closed']], $result3->rows);
    }

    public function testSqlKeepsEachStatementOfAProcedureOnItsOwn(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (a INT PRIMARY KEY)');
        $session->query('CREATE PROCEDURE p() BEGIN INSERT INTO t VALUES (1); INSERT INTO t VALUES (2), (1); END');
        $answers = $session->run('CALL p()');

        $result4 = $session->query('SELECT * FROM t')[0];
        self::assertInstanceOf(\MySqlMemory\Result\ResultSet::class, $result4);
        self::assertInstanceOf(\MySqlMemory\Error\SqlError::class, $answers[0]);
        self::assertSame([1062, [['1']]], [$answers[0]->getCode(), $result4->rows]);
    }

    public function testLocalTellsWhetherASetAssignsOnlyVariablesOfTheProgram(): void
    {
        $session = (new Instance())->connect();
        $create = (new Semantics(Dialect::MySql))->analyze('CREATE PROCEDURE p() BEGIN DECLARE v INT; SET v = 1; SET v = 1, @u = 2; END');
        $activation = new Activation('PROCEDURE', 'd.p', false, Collation::known('utf8mb4_0900_ai_ci'));
        $interpreter = new Interpreter($session, $activation);
        $statement = $create->statement;
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Routine\CreateProcedure::class, $statement);
        $block = $statement->body;
        self::assertInstanceOf(Block::class, $block);
        $interpreter->declarations($block);
        [$local, $mixed] = $block->statements;
        self::assertInstanceOf(SetVariables::class, $local);
        self::assertInstanceOf(SetVariables::class, $mixed);

        self::assertSame([true, false], [$interpreter->local($local), $interpreter->local($mixed)]);
    }

    public function testInstructionLeavesRowCountAsItWasAfterAnAssignmentOfVariables(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (a INT)');
        $session->query('CREATE PROCEDURE p() BEGIN DECLARE v INT; INSERT INTO t VALUES (1), (2); SET v = 1; IF v THEN SET @r = ROW_COUNT(); END IF; END');

        $session->query('CALL p()');

        $result5 = $session->query('SELECT @r')[0];
        self::assertInstanceOf(\MySqlMemory\Result\ResultSet::class, $result5);
        self::assertSame([['2']], $result5->rows);
    }

    public function testBlockLeavesItsDeclarationsAtItsEnd(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE PROCEDURE p() BEGIN DECLARE v INT DEFAULT 1; b: BEGIN DECLARE v INT DEFAULT 2; SELECT v; LEAVE b; SELECT 0; END b; SELECT v; END');

        $replies = $session->query('CALL p()');

        self::assertInstanceOf(\MySqlMemory\Result\ResultSet::class, $replies[0]);
        self::assertInstanceOf(\MySqlMemory\Result\ResultSet::class, $replies[1]);
        self::assertSame([[['2']], [['1']]], [$replies[0]->rows, $replies[1]->rows]);
    }

    public function testDeclarationsGiveVariablesTheirDefaultOrNull(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE PROCEDURE p() BEGIN DECLARE a, b INT DEFAULT 5; DECLARE c INT; SELECT a, b, c; END');

        $result6 = $session->query('CALL p()')[0];
        self::assertInstanceOf(\MySqlMemory\Result\ResultSet::class, $result6);
        self::assertSame([['5', '5', null]], $result6->rows);
    }

    public function testDeclareStoresTheDefaultAsIntoAColumn(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE PROCEDURE p() BEGIN DECLARE d DECIMAL(5,2) DEFAULT 1.234; SELECT d; END');

        $result7 = $session->query('CALL p()')[0];
        self::assertInstanceOf(\MySqlMemory\Result\ResultSet::class, $result7);
        self::assertSame([['1.23']], $result7->rows);
    }

    public function testBranchesRefusesACaseWithoutAMatchingBranch(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query("CREATE PROCEDURE p() BEGIN DECLARE v INT DEFAULT 3; CASE v WHEN 1 THEN SELECT 'one'; END CASE; END");

        $this->expectExceptionCode(1339);
        $this->expectExceptionMessage('Case not found for CASE statement');

        $session->query('CALL p()');
    }

    public function testLoopRepeatsUntilItsCondition(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE PROCEDURE p() BEGIN DECLARE i INT DEFAULT 0; REPEAT SET i = i + 1; UNTIL i >= 3 END REPEAT; SELECT i; END');

        $result8 = $session->query('CALL p()')[0];
        self::assertInstanceOf(\MySqlMemory\Result\ResultSet::class, $result8);
        self::assertSame([['3']], $result8->rows);
    }

    public function testTestGoesOnAfterTheStatementWhenAContinueHandlerHandlesItsCondition(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (a INT)');
        $session->query('INSERT INTO t VALUES (10), (11)');
        $session->query("CREATE PROCEDURE p() BEGIN DECLARE CONTINUE HANDLER FOR SQLEXCEPTION SET @w = 'h'; SET @w = ''; IF (SELECT a FROM t) THEN SET @w = 'then'; END IF; SET @w = CONCAT(@w, 'after'); SELECT @w; END");

        $result9 = $session->query('CALL p()')[0];
        self::assertInstanceOf(\MySqlMemory\Result\ResultSet::class, $result9);
        self::assertSame([['hafter']], $result9->rows);
    }

    public function testReturnedStoresTheValueStrictly(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE FUNCTION f() RETURNS INT DETERMINISTIC RETURN 1/0');

        $this->expectExceptionCode(1365);
        $this->expectExceptionMessage('Division by 0');

        $session->query('SELECT f()');
    }
}
