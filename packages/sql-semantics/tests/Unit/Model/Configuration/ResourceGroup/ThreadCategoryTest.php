<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Configuration\ResourceGroup;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Model\Configuration\ResourceGroup\ThreadCategory;

#[CoversClass(ThreadCategory::class)]
final class ThreadCategoryTest extends TestCase
{
    public function testHighestPriorityIsZeroForUserThreadsAndMinusTwentyForSystemThreads(): void
    {
        self::assertSame([0, -20], [ThreadCategory::User->highestPriority(), ThreadCategory::System->highestPriority()]);
    }

    public function testLowestPriorityIsNineteenForUserThreadsAndZeroForSystemThreads(): void
    {
        self::assertSame([19, 0], [ThreadCategory::User->lowestPriority(), ThreadCategory::System->lowestPriority()]);
    }
}
