<?php

declare(strict_types=1);

namespace Tests\Unit\Plan;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFixture\Plan\ColumnRef;
use SqlFixture\Plan\GenerationOrder;
use SqlFixture\Plan\PlanStructureException;
use SqlFixture\Plan\Relation;
use SqlFixture\Plan\RelationKind;
use SqlFixture\Plan\RelationSide;

#[CoversClass(GenerationOrder::class)]
#[UsesClass(ColumnRef::class)]
#[UsesClass(PlanStructureException::class)]
#[UsesClass(Relation::class)]
#[UsesClass(RelationKind::class)]
#[UsesClass(RelationSide::class)]
final class GenerationOrderTest extends TestCase
{
    public function testOfPutsAParentBeforeItsChild(): void
    {
        self::assertSame(
            ['order', 'detail'],
            (new GenerationOrder())->of(['detail', 'order'], [Relation::oneToMany('order.id', 'detail.order_id')]),
        );
    }

    public function testOfOrdersAChainOfTablesFromTheTopDown(): void
    {
        self::assertSame(
            ['customer', 'order', 'detail'],
            (new GenerationOrder())->of(['detail', 'order', 'customer'], [
                Relation::oneToMany('order.id', 'detail.order_id'),
                Relation::oneToMany('customer.id', 'order.customer_id'),
            ]),
        );
    }

    public function testOfLeavesTablesNoRelationTouchesInTheOrderTheyWereNamed(): void
    {
        self::assertSame(['a', 'b'], (new GenerationOrder())->of(['a', 'b'], []));
    }

    public function testOfDoesNotMakeATableWaitForItself(): void
    {
        self::assertSame(
            ['node'],
            (new GenerationOrder())->of(['node'], [Relation::oneToMany('node.id', 'node.parent_id', true)]),
        );
    }

    public function testOfRefusesTablesEachWaitingOnTheNext(): void
    {
        $this->expectException(PlanStructureException::class);

        (new GenerationOrder())->of(['a', 'b'], [
            Relation::oneToMany('a.id', 'b.a_id'),
            Relation::oneToMany('b.id', 'a.b_id'),
        ]);
    }

    public function testWaitsForAnyReportsATableWhoseParentIsStillPending(): void
    {
        $relations = [Relation::oneToMany('order.id', 'detail.order_id')];

        self::assertTrue((new GenerationOrder())->waitsForAny('detail', $relations, ['order', 'detail']));
        self::assertFalse((new GenerationOrder())->waitsForAny('detail', $relations, ['detail']));
    }
}
