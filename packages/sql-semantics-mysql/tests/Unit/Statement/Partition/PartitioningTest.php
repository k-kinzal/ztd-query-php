<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Partition;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Partition\Partitioning;
use SqlSemantics\Statement\Node;

#[CoversNothing]
#[Small]
final class PartitioningTest extends TestCase
{
    public function testAPartitioningIsARenderableNode(): void
    {
        self::assertTrue(interface_exists(Partitioning::class));
        self::assertContains(Node::class, class_implements(Partitioning::class));
    }
}
