<?php

declare(strict_types=1);

namespace Tests\Unit\Command\Condition;

use MySqlMemory\Command\Condition\SignalCommand;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Instance;
use MySqlMemory\Result\Completion;
use MySqlMemory\Result\ResultSet;
use MySqlMemory\Typing\Domain;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Literal\Text;
use SqlSemantics\Platform\MySql\Statement\Routine\Condition\Resignal;
use SqlSemantics\Platform\MySql\Statement\Routine\Condition\SqlState;

#[CoversClass(SignalCommand::class)]
#[Small]
final class SignalCommandTest extends TestCase
{
    public function testResignalKeepsTheDiagnosticsAreaOfAValidStatement(): void
    {
        self::assertFalse(SignalCommand::resignal(new Resignal(null))->clearsDiagnostics());
    }

    public function testResignalClearsTheDiagnosticsAreaOfABadSqlstate(): void
    {
        self::assertTrue(SignalCommand::resignal(new Resignal(new SqlState(new Text('x'))))->clearsDiagnostics());
    }

    public function testClearsDiagnosticsAnswersTrueForSignal(): void
    {
        self::assertTrue((new SignalCommand())->clearsDiagnostics());
    }

    public function testExecuteRaisesTheUserDefinedException(): void
    {
        $session = (new Instance())->connect();

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1644);
        $this->expectExceptionMessage('Unhandled user-defined exception condition');

        $session->query("SIGNAL SQLSTATE '45000'");
    }

    public function testExecuteRaisesTheNumberTextAndSqlstateItSets(): void
    {
        $session = (new Instance())->connect();

        $error = $session->run("SIGNAL SQLSTATE '45001' SET MESSAGE_TEXT = 'boom', MYSQL_ERRNO = 5001")[0];
        self::assertInstanceOf(SqlError::class, $error);
        self::assertSame([5001, '45001', 'boom'], [$error->getCode(), $error->sqlState(), $error->getMessage()]);
    }

    public function testExecuteRaisesAWarningForClass01(): void
    {
        $session = (new Instance())->connect();

        $reply = $session->query("SIGNAL SQLSTATE '01234' SET MESSAGE_TEXT = 'w', MYSQL_ERRNO = 1000")[0];

        self::assertInstanceOf(Completion::class, $reply);
        $result1 = $session->query('SHOW WARNINGS')[0];
        self::assertInstanceOf(ResultSet::class, $result1);
        self::assertSame([['Warning', '1000', 'w']], $result1->rows);
    }

    public function testExecuteRaisesTheNotFoundConditionForClass02(): void
    {
        $session = (new Instance())->connect();

        $this->expectExceptionCode(1643);
        $this->expectExceptionMessage('Unhandled user-defined not found condition');

        $session->query("SIGNAL SQLSTATE '02000'");
    }

    public function testExecuteRefusesAMessageOfMoreThan128Characters(): void
    {
        $session = (new Instance())->connect();

        $this->expectExceptionCode(1648);
        $this->expectExceptionMessage("Data too long for condition item 'MESSAGE_TEXT'");

        $session->query("SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = '" . str_repeat('x', 129) . "'");
    }

    public function testExecuteReadsMessageTextBeforeMysqlErrno(): void
    {
        $session = (new Instance())->connect();

        $this->expectExceptionCode(1231);
        $this->expectExceptionMessage("Variable 'MESSAGE_TEXT' can't be set to the value of 'NULL'");

        $session->query("SIGNAL SQLSTATE '45000' SET MYSQL_ERRNO = 0, MESSAGE_TEXT = NULL");
    }

    public function testExecuteRefusesResignalWithoutAHandler(): void
    {
        $session = (new Instance())->connect();

        $this->expectExceptionCode(1645);
        $this->expectExceptionMessage('RESIGNAL when handler not active');

        $session->query('RESIGNAL');
    }

    public function testNumberRoundsADecimal(): void
    {
        $session = (new Instance())->connect();

        $this->expectExceptionCode(2);

        $session->query("SIGNAL SQLSTATE '45000' SET MYSQL_ERRNO = 1.5");
    }

    public function testNumberRefusesZero(): void
    {
        $session = (new Instance())->connect();
        $context = new \MySqlMemory\Evaluation\Context($session->modes(), $session->diagnostics, $session->variables, 0.0);

        $this->expectExceptionCode(1231);
        $this->expectExceptionMessage("Variable 'MYSQL_ERRNO' can't be set to the value of '0'");

        (new SignalCommand())->number(0, Domain::integer(), $context);
    }

    public function testNumberWarnsOfADecimalBeyondTheIntegers(): void
    {
        $session = (new Instance())->connect();

        $session->run("SIGNAL SQLSTATE '45000' SET MYSQL_ERRNO = 18446744073709551616");

        self::assertSame([['Warning', 1292, "Truncated incorrect DECIMAL value: '18446744073709551616'"], ['Error', 1231, "Variable 'MYSQL_ERRNO' can't be set to the value of '18446744073709551616'"]], $session->diagnostics->conditions);
    }
}
