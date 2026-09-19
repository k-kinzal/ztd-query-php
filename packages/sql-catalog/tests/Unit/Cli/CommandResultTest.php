<?php

declare(strict_types=1);

namespace Tests\Unit\Cli;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlCatalog\Cli\CommandResult;
use SqlCatalog\Cli\ExitCode;

#[CoversClass(CommandResult::class)]
#[UsesClass(ExitCode::class)]
final class CommandResultTest extends TestCase
{
    public function testOkWritesOnlyToStandardOutput(): void
    {
        $result = CommandResult::ok('done');
        self::assertSame(ExitCode::Success, $result->exitCode);
        self::assertSame('done', $result->output);
        self::assertSame('', $result->error);
    }

    public function testFailureWritesOnlyToStandardErrorAndEndsWithANewline(): void
    {
        $result = CommandResult::failure(ExitCode::InvalidCommandLine, "bad\n\n");
        self::assertSame(ExitCode::InvalidCommandLine, $result->exitCode);
        self::assertSame('', $result->output);
        self::assertSame("bad\n", $result->error);
    }
}
