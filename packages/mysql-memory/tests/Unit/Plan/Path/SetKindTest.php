<?php

declare(strict_types=1);

namespace Tests\Unit\Plan\Path;

use MySqlMemory\Plan\Path\SetKind;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(SetKind::class)]
#[Small]
final class SetKindTest extends TestCase
{
    public function testCasesAreUnionIntersectAndExcept(): void
    {
        self::assertSame(['Union', 'Intersect', 'Except'], array_map(static fn (SetKind $kind): string => $kind->name, SetKind::cases()));
    }
}
