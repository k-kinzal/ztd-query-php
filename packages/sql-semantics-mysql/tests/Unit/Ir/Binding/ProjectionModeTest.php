<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Binding;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Ir\Binding\ProjectionMode;

#[CoversClass(ProjectionMode::class)]
#[Small]
final class ProjectionModeTest extends TestCase
{
    public function testCasesIdentifyTheCheckedSqlModes(): void
    {
        self::assertSame(['empty', 'ANSI_QUOTES', 'NO_BACKSLASH_ESCAPES', 'ANSI_QUOTES,NO_BACKSLASH_ESCAPES'], array_column(ProjectionMode::cases(), 'value'));
    }

    public function testEscapesBackslashFollowsTheOwningMode(): void
    {
        self::assertTrue(ProjectionMode::Empty->escapesBackslash());
        self::assertTrue(ProjectionMode::AnsiQuotes->escapesBackslash());
        self::assertFalse(ProjectionMode::NoBackslashEscapes->escapesBackslash());
        self::assertFalse(ProjectionMode::AnsiQuotesAndNoBackslashEscapes->escapesBackslash());
    }
}
