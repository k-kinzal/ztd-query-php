<?php

declare(strict_types=1);

namespace Tests\Unit\Plan\Path;

use MySqlMemory\Plan\Path\JoinKind;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(JoinKind::class)]
#[Small]
final class JoinKindTest extends TestCase
{
    public function testCasesAreInnerLeftAndRight(): void
    {
        self::assertSame(['Inner', 'Left', 'Right'], array_map(static fn (JoinKind $kind): string => $kind->name, JoinKind::cases()));
    }
}
