<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\Routine\Stored;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Model\Definition\Routine\Stored\TriggerOrdering;

#[CoversClass(TriggerOrdering::class)]
final class TriggerOrderingTest extends TestCase
{
    public function testSpellsBothPositions(): void
    {
        self::assertSame(['FOLLOWS', 'PRECEDES'], array_column(TriggerOrdering::cases(), 'value'));
    }
}
