<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Partition;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Partition\PartitionSelection;
use SqlSemantics\Statement\Node;

#[CoversNothing]
#[Small]
final class PartitionSelectionTest extends TestCase
{
    public function testAPartitionSelectionIsARenderableNode(): void
    {
        self::assertTrue(interface_exists(PartitionSelection::class));
        self::assertContains(Node::class, class_implements(PartitionSelection::class));
    }
}
