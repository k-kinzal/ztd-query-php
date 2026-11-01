<?php

declare(strict_types=1);

namespace Tests\Unit\Plan\Path;

use MySqlMemory\Plan\Path\AccessPath;
use MySqlMemory\Plan\Path\Combine\NestedLoopJoin;
use MySqlMemory\Plan\Path\JoinKind;
use MySqlMemory\Plan\Path\Source\SingleRow;
use MySqlMemory\Plan\Path\Source\ZeroRows;
use MySqlMemory\Plan\Path\Transform\Limit;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(AccessPath::class)]
#[Small]
final class AccessPathTest extends TestCase
{
    public function testWidthOfATreeFollowsTheWidthsOfItsPaths(): void
    {
        $path = new Limit(new NestedLoopJoin(new ZeroRows(2), new NestedLoopJoin(new SingleRow(), new ZeroRows(1), JoinKind::Inner, null), JoinKind::Inner, null), 1);

        $widths = array_map(static fn (AccessPath $path): int => $path->width(), [$path, $path->input, new SingleRow()]);

        self::assertSame([3, 3, 0], $widths);
    }
}
