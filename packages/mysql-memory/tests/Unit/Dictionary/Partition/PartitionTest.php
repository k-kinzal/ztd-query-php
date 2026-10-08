<?php

declare(strict_types=1);

namespace Tests\Unit\Dictionary\Partition;

use MySqlMemory\Dictionary\Partition\Partition;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(Partition::class)]
#[Small]
final class PartitionTest extends TestCase
{
    public function testBoundAndValuesDescribeWhatThePartitionHolds(): void
    {
        $partition = new Partition('p0', [10], [[1], [null]]);

        self::assertSame(['p0', [10], [[1], [null]]], [$partition->name, $partition->bound, $partition->values]);
    }
}
