<?php

declare(strict_types=1);

namespace Tests\Unit\Cli;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlCatalog\Cli\ExitCode;

#[CoversClass(ExitCode::class)]
final class ExitCodeTest extends TestCase
{
    public function testSuccessIsZeroAndEveryFailureIsNot(): void
    {
        self::assertSame(0, ExitCode::Success->value);
        self::assertSame([1, 2, 3], [
            ExitCode::FindingsReported->value,
            ExitCode::InvalidCommandLine->value,
            ExitCode::SourceUnreadable->value,
        ]);
    }
}
