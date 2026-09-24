<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Configuration\ResourceGroup;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Model\Configuration\ResourceGroup\CpuRange;
use SqlSemantics\Model\Validation\InvalidStructure;

#[CoversClass(CpuRange::class)]
final class CpuRangeTest extends TestCase
{
    public function testKeepsInclusiveBounds(): void
    {
        $range = new CpuRange(2, 5);
        self::assertSame([2, 5], [$range->first, $range->last]);
    }

    #[TestWith([-1, 0])]
    #[TestWith([3, 2])]
    public function testRejectsReversedOrNegativeBounds(int $first, int $last): void
    {
        $this->expectException(InvalidStructure::class);
        new CpuRange($first, $last);
    }
}
