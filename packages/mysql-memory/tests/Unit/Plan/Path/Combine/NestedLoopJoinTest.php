<?php

declare(strict_types=1);

namespace Tests\Unit\Plan\Path\Combine;

use MySqlMemory\Plan\Path\Combine\NestedLoopJoin;
use MySqlMemory\Plan\Path\JoinKind;
use MySqlMemory\Plan\Path\Source\ZeroRows;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(NestedLoopJoin::class)]
#[Small]
final class NestedLoopJoinTest extends TestCase
{
    public function testWidthAddsTheWidthsOfBothInputs(): void
    {
        $join = new NestedLoopJoin(new ZeroRows(2), new ZeroRows(3), JoinKind::Left, null);

        self::assertSame(5, $join->width());
        self::assertFalse($join->lateral);
    }
}
