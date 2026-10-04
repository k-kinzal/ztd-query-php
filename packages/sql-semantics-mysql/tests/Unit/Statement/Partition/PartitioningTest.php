<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Partition;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Partition\PartitionClause;
use SqlSemantics\Platform\MySql\Statement\Partition\Partitioning;

#[CoversNothing]
#[Small]
final class PartitioningTest extends TestCase
{
    public function testDerivePartitioningIsDeclaredByThePartitioningOfATable(): void
    {
        self::assertContains(Partitioning::class, class_implements(PartitionClause::class));
    }
}
