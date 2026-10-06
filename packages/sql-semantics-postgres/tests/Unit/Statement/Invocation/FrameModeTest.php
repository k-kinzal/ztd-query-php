<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Invocation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\FrameMode;

#[CoversClass(FrameMode::class)]
#[Small]
final class FrameModeTest extends TestCase
{
    public function testCasesSpellTheModes(): void
    {
        self::assertSame(['RANGE', 'ROWS', 'GROUPS'], array_map(static fn (FrameMode $mode): string => $mode->value, FrameMode::cases()));
    }
}
