<?php

declare(strict_types=1);

namespace Tests\Unit\Dictionary\Partition;

use MySqlMemory\Dictionary\Partition\Partition;
use MySqlMemory\Dictionary\Partition\Partitioning;
use MySqlMemory\Dictionary\Partition\PartitionMethod;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(Partitioning::class)]
#[Small]
final class PartitioningTest extends TestCase
{
    public function testPartitionFindsAPartitionWithoutRegardToCase(): void
    {
        $partitioning = new Partitioning(PartitionMethod::Key, null, [0], [new Partition('p0'), new Partition('P1')]);

        self::assertSame([1, null], [$partitioning->partition('p1'), $partitioning->partition('p2')]);
    }
}
