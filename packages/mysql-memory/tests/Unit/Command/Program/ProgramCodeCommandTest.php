<?php

declare(strict_types=1);

namespace Tests\Unit\Command\Program;

use MySqlMemory\Command\Program\ProgramCodeCommand;
use MySqlMemory\Evaluation\Compile\Connection;
use MySqlMemory\Evaluation\Context;
use MySqlMemory\Instance;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(ProgramCodeCommand::class)]
#[Small]
final class ProgramCodeCommandTest extends TestCase
{
    public function testClearsDiagnosticsAnswersTrue(): void
    {
        self::assertTrue((new ProgramCodeCommand())->clearsDiagnostics());
    }

    public function testExecuteRefusesTheStatementOfDebugBuilds(): void
    {
        $session = (new Instance())->connect();
        $operation = $session->analyze('SHOW PROCEDURE CODE p');
        $context = new Context($session->modes(), $session->diagnostics, $session->variables, 0.0);

        $this->expectExceptionCode(1289);
        $this->expectExceptionMessage("The 'SHOW PROCEDURE|FUNCTION CODE' feature is disabled; you need MySQL built with '--with-debug' to have it working");

        (new ProgramCodeCommand())->execute($operation, $session, $context, new Connection($session->variables, $context, 'root', 'localhost', 1, []));
    }

    public function testExecuteIsRefusedForAFunctionToo(): void
    {
        $session = (new Instance())->connect();

        $this->expectExceptionCode(1289);

        $session->query('SHOW FUNCTION CODE SUPER .BYTE');
    }
}
