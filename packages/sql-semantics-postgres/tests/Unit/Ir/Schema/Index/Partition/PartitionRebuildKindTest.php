<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\Index\Partition;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Index\Partition\PartitionRebuildKind;

#[CoversClass(PartitionRebuildKind::class)]
#[Small]
final class PartitionRebuildKindTest extends TestCase
{
    public function testDistinguishesThePartitionRoles(): void
    {
        self::assertSame('branch', PartitionRebuildKind::Branch->value);
        self::assertSame('leaf', PartitionRebuildKind::Leaf->value);
    }
}
