<?php

declare(strict_types=1);

namespace Tests\Unit\Dictionary\Partition;

use MySqlMemory\Dictionary\Partition\PartitionMethod;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(PartitionMethod::class)]
#[Small]
final class PartitionMethodTest extends TestCase
{
    public function testCasesNameEveryMethod(): void
    {
        self::assertSame(['Range', 'RangeColumns', 'List', 'ListColumns', 'Hash', 'Key'], array_map(static fn (PartitionMethod $method): string => $method->name, PartitionMethod::cases()));
    }
}
