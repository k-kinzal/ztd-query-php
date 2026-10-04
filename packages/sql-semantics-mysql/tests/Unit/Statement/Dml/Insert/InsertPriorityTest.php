<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Dml\Insert;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Dml\Insert\InsertPriority;

#[CoversClass(InsertPriority::class)]
#[Small]
final class InsertPriorityTest extends TestCase
{
    public function testCasesHoldTheirKeywords(): void
    {
        self::assertSame(['LOW_PRIORITY', 'DELAYED', 'HIGH_PRIORITY'], array_map(static fn (InsertPriority $priority): string => $priority->value, InsertPriority::cases()));
    }
}
