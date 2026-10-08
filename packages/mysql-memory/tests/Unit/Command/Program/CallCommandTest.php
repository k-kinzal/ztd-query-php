<?php

declare(strict_types=1);

namespace Tests\Unit\Command\Program;

use MySqlMemory\Command\Program\CallCommand;
use MySqlMemory\Evaluation\Compile\Connection;
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

    public function testArgumentsStoresEachArgumentAsIntoAColumn(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE PROCEDURE p(x INT, s VARCHAR(5)) SELECT x, s');
        $routine = $session->instance->dictionary->schema('d')?->procedures['p'];
        self::assertNotNull($routine);
        $operation = $session->analyze("CALL p(1.7, 'abcdefg')");
        $statement = $operation->statement;
        self::assertInstanceOf(ProcedureCall::class, $statement);
        $context = new Context($session->modes(), $session->diagnostics, $session->variables, 0.0);

        $this->expectExceptionCode(1406);
        $this->expectExceptionMessage("Data too long for column 's' at row 1");

        (new CallCommand())->arguments($statement, $routine, $operation, $session, $context, new Connection($session->variables, $context, 'root', 'localhost', 1, []));
    }

    public function testStatementsRefusesABodyWithDeclarations(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE PROCEDURE q(x INT) BEGIN DECLARE y INT; SET y = x; SELECT y; END');
        $routine = $session->instance->dictionary->schema('d')?->procedures['q'];
        self::assertNotNull($routine);

        $this->expectExceptionCode(1235);

        (new CallCommand())->statements($routine, $session);
    }

    public function testStatementWritesTheParametersAsMarkersAndKeepsTheNames(): void
    {
        $session = (new Instance())->connect();
        $text = 'CREATE PROCEDURE p(x INT) SELECT x, x + 1 AS y, a FROM t LIMIT x';
        $member = $session->semantics()->parser()->parse($text)->find('sp_proc_stmt')[0];

        self::assertSame(['SELECT ? AS `x`, ? + 1 AS y, a FROM t LIMIT ?', [0, 0, 0]], (new CallCommand())->statement($member, $text, ['x']));
    }
}
