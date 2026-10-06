<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Partition\Bound;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Partition\Bound\LessThan;
use SqlSemantics\Platform\MySql\Statement\Partition\Bound\PartitionBound;

#[CoversNothing]
#[Small]
final class PartitionBoundTest extends TestCase
{
    public function testDeriveBoundIsDeclaredByEveryBound(): void
    {
        self::assertContains(PartitionBound::class, class_implements(LessThan::class));
    }
}
