<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Routine\Trigger;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Routine\Trigger\OrderPlacement;

#[CoversClass(OrderPlacement::class)]
#[Small]
final class OrderPlacementTest extends TestCase
{
    public function testCasesHoldTheirKeywords(): void
    {
        self::assertSame(['FOLLOWS', 'PRECEDES'], array_map(static fn (OrderPlacement $placement): string => $placement->value, OrderPlacement::cases()));
    }
}
