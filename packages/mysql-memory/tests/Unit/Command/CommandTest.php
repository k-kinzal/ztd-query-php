<?php

declare(strict_types=1);

namespace Tests\Unit\Command;

use MySqlMemory\Command\Command;
use MySqlMemory\Command\Dispatcher;
use MySqlMemory\Evaluation\Compile\Connection;
use MySqlMemory\Evaluation\Context;
use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(Command::class)]
#[Small]
final class CommandTest extends TestCase
{
    public function testClearsDiagnosticsIsFalseOnlyForTheStatementsThatReadTheArea(): void
    {
        $session = (new Instance())->connect();
        $dispatcher = new Dispatcher();

        self::assertSame(
            [true, true, false, false],
            [
                $dispatcher->command($session->analyze('SELECT 1')->statement)->clearsDiagnostics(),
                $dispatcher->command($session->analyze('SET @a = 1')->statement)->clearsDiagnostics(),
                $dispatcher->command($session->analyze('SHOW WARNINGS')->statement)->clearsDiagnostics(),
                $dispatcher->command($session->analyze('SHOW ERRORS')->statement)->clearsDiagnostics(),
            ],
        );
    }

    public function testExecuteAnswersTheReplyOfAResolvedStatement(): void
    {
        $session = (new Instance())->connect();
        $operation = $session->analyze('SELECT 1 + 1');
        $context = new Context($session->modes(), $session->diagnostics, $session->variables, 0.0);
        $command = (new Dispatcher())->command($operation->statement);

        $reply = $command->execute($operation, $session, $context, new Connection($session->variables, $context));

        self::assertInstanceOf(ResultSet::class, $reply);
        self::assertSame([['2']], $reply->rows);
    }
}
