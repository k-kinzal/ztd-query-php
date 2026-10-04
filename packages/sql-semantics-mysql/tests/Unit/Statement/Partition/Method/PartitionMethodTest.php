<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Partition\Method;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Partition\Method\KeyMethod;
use SqlSemantics\Platform\MySql\Statement\Partition\Method\PartitionMethod;

#[CoversNothing]
#[Small]
final class PartitionMethodTest extends TestCase
{
    public function testDeriveMethodIsDeclaredByEveryMethod(): void
    {
        self::assertContains(PartitionMethod::class, class_implements(KeyMethod::class));
    }
}
