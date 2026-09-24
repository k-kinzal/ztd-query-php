<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\Replication\Subscription;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Replication\Subscription as Operand;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(Operand\SubscriptionInvariant::class)]
#[Medium]
final class SubscriptionInvariantTest extends TestCase
{
    #[TestWith([Dialect::MySql, 's'])]
    #[TestWith([Dialect::PostgreSql, ''])]
    public function testIdentityRejectsAnotherLanguageOrAnEmptyName(Dialect $dialect, string $name): void
    {
        $origin = (new Binder((new SchemaBuilder($dialect))->build()))->bind('SELECT 1')->origin;
        $this->expectException(InvalidStructure::class);
        Operand\SubscriptionInvariant::identity($origin, $name);
    }

    /**
     * @param list<string> $publications
     */
    #[TestWith([[]])]
    #[TestWith([['a', 'a']])]
    #[TestWith([['']])]
    public function testPublicationsAreDistinctAndNonempty(array $publications): void
    {
        $this->expectException(InvalidStructure::class);
        Operand\SubscriptionInvariant::publications($publications);
    }

    public function testOptionsRejectAnOptionOfAnotherCommand(): void
    {
        Operand\SubscriptionInvariant::options(new Operand\SubscriptionOptions(refresh: true), [Operand\SubscriptionParameter::Refresh]);
        $this->expectException(InvalidStructure::class);
        Operand\SubscriptionInvariant::options(new Operand\SubscriptionOptions(binary: true), [Operand\SubscriptionParameter::Refresh]);
    }

    public function testCreationAcceptsADetachedSubscription(): void
    {
        $options = new Operand\SubscriptionOptions(slotName: Operand\NoSlot::None, enabled: false, createSlot: false);
        Operand\SubscriptionInvariant::creation($options);
        Operand\SubscriptionInvariant::creation(new Operand\SubscriptionOptions(connect: false, slotName: Operand\NoSlot::None));
        self::assertSame(Operand\NoSlot::None, $options->slotName);
    }

    #[TestWith([false, true, null, null, null])]
    #[TestWith([false, null, true, null, null])]
    #[TestWith([false, null, null, true, null])]
    #[TestWith([null, null, false, null, true])]
    #[TestWith([null, false, null, null, true])]
    public function testCreationRejectsExclusiveOptions(?bool $connect, ?bool $enabled, ?bool $createSlot, ?bool $copyData, ?bool $noSlot): void
    {
        $this->expectException(InvalidStructure::class);
        Operand\SubscriptionInvariant::creation(new Operand\SubscriptionOptions($connect, $enabled, $createSlot, $noSlot === true ? Operand\NoSlot::None : null, $copyData));
    }
}
