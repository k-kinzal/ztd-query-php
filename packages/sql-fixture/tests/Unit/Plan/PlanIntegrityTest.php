<?php

declare(strict_types=1);

namespace Tests\Unit\Plan;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFixture\Plan\ColumnRef;
use SqlFixture\Plan\PlanIntegrity;
use SqlFixture\Plan\PlanPrinter;
use SqlFixture\Plan\PlanStructureException;
use SqlFixture\Plan\Relation;
use SqlFixture\Plan\RelationKind;
use SqlFixture\Plan\RelationSide;

#[CoversClass(PlanIntegrity::class)]
#[UsesClass(ColumnRef::class)]
#[UsesClass(PlanPrinter::class)]
#[UsesClass(PlanStructureException::class)]
#[UsesClass(Relation::class)]
#[UsesClass(RelationKind::class)]
#[UsesClass(RelationSide::class)]
final class PlanIntegrityTest extends TestCase
{
    public function testAssertColumnsBoundOnceAcceptsRelationsThatBindDifferentColumns(): void
    {
        (new PlanIntegrity())->assertColumnsBoundOnce([
            Relation::oneToMany('order.id', 'detail.order_id'),
            Relation::oneToMany('order.id', 'shipment.order_id'),
        ]);

        $this->expectNotToPerformAssertions();
    }

    public function testAssertColumnsBoundOnceRefusesTheSameColumnBoundTwice(): void
    {
        $this->expectException(PlanStructureException::class);

        (new PlanIntegrity())->assertColumnsBoundOnce([
            Relation::oneToMany('order.id', 'detail.order_id'),
            Relation::oneToMany('customer.id', 'detail.order_id'),
        ]);
    }

    public function testAssertNoUnboundedSelfReferenceAcceptsAnOptionalSelfReference(): void
    {
        (new PlanIntegrity())->assertNoUnboundedSelfReference([
            Relation::oneToMany('node.id', 'node.parent_id', true),
        ]);

        $this->expectNotToPerformAssertions();
    }

    public function testAssertNoUnboundedSelfReferenceRefusesARequiredSelfReference(): void
    {
        $this->expectException(PlanStructureException::class);

        (new PlanIntegrity())->assertNoUnboundedSelfReference([
            Relation::oneToMany('node.id', 'node.parent_id'),
        ]);
    }

    public function testAssertNoUnboundedSelfReferenceLeavesRelationsBetweenTwoTablesAlone(): void
    {
        (new PlanIntegrity())->assertNoUnboundedSelfReference([
            Relation::oneToMany('order.id', 'detail.order_id'),
        ]);

        $this->expectNotToPerformAssertions();
    }
}
