<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Maintenance\PostgreSql;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Model\Statement\Maintenance\PostgreSql\BufferUsageLimit;
use SqlSemantics\Model\Validation\InvalidStructure;

#[CoversClass(BufferUsageLimit::class)]
#[\PHPUnit\Framework\Attributes\Medium]
final class BufferUsageLimitTest extends TestCase
{
    #[TestWith(['0', 0])]
    #[TestWith(['128kB', 128])]
    #[TestWith(['16GB', 16777216])]
    public function testKilobytesKeepsTheQuantity(string $setting, int $expected): void
    {
        $limit = new BufferUsageLimit($setting);
        self::assertSame($expected, $limit->kilobytes);
        self::assertSame($setting, $limit->setting);
    }

    #[TestWith(['127'])]
    #[TestWith(['17GB'])]
    #[TestWith(['-1'])]
    #[TestWith(['on'])]
    public function testRejectsSizesOutsideTheRingBufferRange(string $setting): void
    {
        $this->expectException(InvalidStructure::class);
        new BufferUsageLimit($setting);
    }
}
