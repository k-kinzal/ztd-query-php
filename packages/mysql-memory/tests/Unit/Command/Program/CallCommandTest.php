<?php

declare(strict_types=1);

namespace Tests\Unit\Command\Program;

use MySqlMemory\Command\Program\CallCommand;
use MySqlMemory\Evaluation\Context;
use MySqlMemory\Instance;
use MySqlMemory\Result\Completion;
use MySqlMemory\Result\ResultSet;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Dml\ProcedureCall;

#[CoversClass(CallCommand::class)]
#[Small]
final class CallCommandTest extends TestCase
{
    public function testClearsDiagnosticsAnswersTrue(): void
    {
        self::assertTrue((new CallCommand())->clearsDiagnostics());
    }

    public function testExecuteAnswersTheResultSetsAndACompletion(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (a INT)');
        $session->query('CREATE PROCEDURE p(x INT) BEGIN INSERT INTO t VALUES (x); SELECT x, x + 1, COUNT(*) FROM t; END');

        $replies = $session->query('CALL p(5)');

        self::assertCount(2, $replies);
        self::assertInstanceOf(ResultSet::class, $replies[0]);
        self::assertInstanceOf(Completion::class, $replies[1]);
        self::assertSame([['5', '6', '1']], $replies[0]->rows);
        self::assertSame(['x', 'x + 1', 'COUNT(*)'], array_map(static fn ($column): string => $column->name, $replies[0]->columns));
    }

    public function testExecuteAnswersTheCompletionOfABodyWithoutQueries(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (a INT)');
        $session->query('CREATE PROCEDURE p(x INT) INSERT INTO t VALUES (x), (x)');

        $reply = $session->query('CALL p(1)')[0];

        self::assertInstanceOf(Completion::class, $reply);
        self::assertSame(2, $reply->affectedRows);
    }

    public function testExecuteRefusesAMissingProcedure(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');

        $this->expectExceptionCode(1305);
        $this->expectExceptionMessage('PROCEDURE d.nope does not exist');

        $session->query('CALL nope');
    }

    public function testExecuteRefusesAnotherNumberOfArguments(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE PROCEDURE p(a INT) SELECT a');

        $this->expectExceptionCode(1318);
        $this->expectExceptionMessage('Incorrect number of arguments for PROCEDURE d.p; expected 1, got 2');

        $session->query('CALL p(1, 2)');
    }

    public function testExecuteGivesTheVariablesTheValuesOfOutAndInoutParameters(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE PROCEDURE p(OUT x INT, INOUT y INT) BEGIN SET x = 5; SET y = y + 1; END');
        $session->query('SET @y = 1');
        $session->query('CALL p(@x, @y)');

        $result1 = $session->query('SELECT @x, @y')[0];
        self::assertInstanceOf(ResultSet::class, $result1);
        self::assertSame([['5', '2']], $result1->rows);
    }

    public function testExecuteRefusesRecursionBeyondMaxSpRecursionDepth(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE PROCEDURE p(n INT) BEGIN IF n > 0 THEN CALL p(n - 1); END IF; END');

        $this->expectExceptionCode(1456);
        $this->expectExceptionMessage('Recursive limit 0 (as set by the max_sp_recursion_depth variable) was exceeded for routine p');

        $session->query('CALL p(1)');
    }

    public function testExecuteAnswersTheResultSetsBeforeTheErrorThatEndsTheProcedure(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query("CREATE PROCEDURE p() BEGIN SELECT 1; SIGNAL SQLSTATE '45000'; END");

        $answers = $session->run('CALL p()');

        self::assertSame([ResultSet::class, \MySqlMemory\Error\SqlError::class], array_map(static fn (object $answer): string => $answer::class, $answers));
    }

    public function testRoutineFindsTheProcedureACallNames(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE PROCEDURE p() SELECT 1');
        $statement = $session->analyze('CALL p()')->statement;
        self::assertInstanceOf(ProcedureCall::class, $statement);

        self::assertSame('p', (new CallCommand())->routine($statement, $session)->name);
    }

    public function testTargetsRefusesAnOutArgumentThatIsNoVariable(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE PROCEDURE p(OUT x INT, INOUT y INT) SET x = 1');

        $this->expectExceptionCode(1414);
        $this->expectExceptionMessage('OUT or INOUT argument 2 for routine d.p is not a variable or NEW pseudo-variable in BEFORE trigger');

        $session->query('CALL p(@x, 2)');
    }

    public function testFieldFindsAColumnOfTheNewRowOfABeforeTrigger(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (a INT)');
        $session->query('CREATE PROCEDURE p(OUT x INT) SET x = 42');
        $session->query('CREATE TRIGGER b BEFORE INSERT ON t FOR EACH ROW CALL p(NEW.a)');
        $session->query('INSERT INTO t VALUES (1)');

        $result2 = $session->query('SELECT a FROM t')[0];
        self::assertInstanceOf(ResultSet::class, $result2);
        self::assertSame([['42']], $result2->rows);
    }

    public function testArgumentsStartsAnOutParameterNull(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE PROCEDURE p(OUT x INT) SELECT x');
        $session->query('SET @x = 5');

        $result3 = $session->query('CALL p(@x)')[0];
        self::assertInstanceOf(ResultSet::class, $result3);
        self::assertSame([[null]], $result3->rows);
    }

    public function testEvaluatedAnswersTheValueAndTypeOfAnArgument(): void
    {
        $session = (new Instance())->connect();
        $context = new Context($session->modes(), $session->diagnostics, $session->variables, 0.0);

        [$value, $domain] = (new CallCommand())->evaluated(new \MySqlMemory\Evaluation\Leaf\Constant(\MySqlMemory\Typing\Domain::integer(), 7), $context);

        self::assertSame([7, \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind::Integer], [$value, $domain->kind]);
    }

    public function testWriteGivesTheVariablesOfTheCallingProgramTheirValuesInOrder(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE PROCEDURE op(OUT x INT, INOUT y INT) BEGIN SET x = 5; SET y = y + 1; END');
        $session->query('CREATE PROCEDURE p() BEGIN DECLARE v INT; CALL op(v, v); SELECT v; END');

        $result4 = $session->query('CALL p()')[0];
        self::assertInstanceOf(ResultSet::class, $result4);
        self::assertSame([[null]], $result4->rows);
    }
}
