<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Configuration\Condition;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Model\Configuration\Condition\ConditionItem;

#[CoversClass(ConditionItem::class)]
final class ConditionItemTest extends TestCase
{
    public function testSignalableExcludesOnlyTheReturnedSqlState(): void
    {
        self::assertSame(['RETURNED_SQLSTATE'], array_values(array_map(static fn (ConditionItem $item): string => $item->value, array_filter(ConditionItem::cases(), static fn (ConditionItem $item): bool => !$item->signalable()))));
        self::assertCount(13, ConditionItem::cases());
    }
}
