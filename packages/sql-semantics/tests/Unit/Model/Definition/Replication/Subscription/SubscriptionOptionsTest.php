<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\Replication\Subscription;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Model\Definition\Replication\Subscription as Operand;
use SqlSemantics\Model\Validation\InvalidStructure;

#[CoversClass(Operand\SubscriptionOptions::class)]
#[Medium]
final class SubscriptionOptionsTest extends TestCase
{
    public function testParametersListOnlySpecifiedOptions(): void
    {
        self::assertSame([], (new Operand\SubscriptionOptions())->parameters());
        self::assertSame([Operand\SubscriptionParameter::SlotName, Operand\SubscriptionParameter::Refresh], (new Operand\SubscriptionOptions(slotName: Operand\NoSlot::None, refresh: false))->parameters());
    }

    public function testValueSpellsEnumeratedValues(): void
    {
        $options = new Operand\SubscriptionOptions(streaming: Operand\StreamingMode::Parallel, origin: Operand\OriginFilter::Any, copyData: false);
        self::assertSame('parallel', $options->value(Operand\SubscriptionParameter::Streaming));
        self::assertSame('any', $options->value(Operand\SubscriptionParameter::Origin));
        self::assertFalse($options->value(Operand\SubscriptionParameter::CopyData));
        self::assertNull($options->value(Operand\SubscriptionParameter::Binary));
    }

    #[TestWith([''])]
    #[TestWith(['Upper'])]
    #[TestWith(['a-b'])]
    public function testASlotNameIsAValidReplicationSlotName(string $name): void
    {
        $this->expectException(InvalidStructure::class);
        new Operand\SubscriptionOptions(slotName: $name);
    }
}
