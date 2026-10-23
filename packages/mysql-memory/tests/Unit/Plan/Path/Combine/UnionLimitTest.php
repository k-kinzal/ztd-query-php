<?php

declare(strict_types=1);

namespace Tests\Unit\Plan\Path\Combine;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(\MySqlMemory\Plan\Path\Combine\UnionLimit::class)]
#[Small]
final class UnionLimitTest extends TestCase
{
    public function testWidthPreservesTheCombinedColumnsIndependentlyOfTheBounds(): void
    {
        $path = new \MySqlMemory\Plan\Path\Combine\UnionLimit(new \MySqlMemory\Plan\Path\Source\WorkingTable(3), 0, 4, true);

        self::assertSame(3, $path->width());
    }

}
