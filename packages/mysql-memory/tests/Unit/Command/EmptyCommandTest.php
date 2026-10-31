<?php

declare(strict_types=1);

namespace Tests\Unit\Command;

use MySqlMemory\Command\EmptyCommand;
use MySqlMemory\Instance;
use MySqlMemory\Result\Completion;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(EmptyCommand::class)]
#[Small]
final class EmptyCommandTest extends TestCase
{
    public function testClearsDiagnosticsRequestsTheNormalStatementArea(): void
    {
        self::assertTrue((new EmptyCommand())->clearsDiagnostics());
    }

    public function testExecuteCompletesACommentAndClearsModernWarnings(): void
    {
        $session = (new Instance())->connect();
        $session->query("SELECT CAST('x' AS SIGNED)");

        $reply = $session->query('/* comment */')[0];

        self::assertInstanceOf(Completion::class, $reply);
        self::assertSame(0, $reply->affectedRows);
        self::assertSame([], $session->diagnostics->conditions);
    }

    public function testExecuteRetainsTheLegacyDiagnosticsArea(): void
    {
        $session = (new Instance('5.6.51'))->connect();
        $session->query("SELECT CAST('x' AS SIGNED)");
        $before = $session->diagnostics->conditions;

        $reply = $session->query('-- comment')[0];

        self::assertInstanceOf(Completion::class, $reply);
        self::assertSame($before, $session->diagnostics->conditions);
    }
}
