<?php

declare(strict_types=1);

namespace Tests\Unit\Plan;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFixture\Plan\ColumnRef;
use SqlFixture\Plan\FixturePlan;
use SqlFixture\Plan\PlanParser;
use SqlFixture\Plan\PlanPrinter;
use SqlFixture\Plan\PlanSyntaxException;
use SqlFixture\Plan\PlanUndefinedException;
use SqlFixture\Plan\Relation;
use SqlFixture\Plan\RelationKind;
use SqlFixture\Plan\RelationSide;
use Tests\Fixture\Plan\OrderWithDetailsPlan;

#[CoversClass(FixturePlan::class)]
#[UsesClass(PlanParser::class)]
#[UsesClass(PlanPrinter::class)]
#[UsesClass(Relation::class)]
#[UsesClass(ColumnRef::class)]
#[UsesClass(RelationKind::class)]
#[UsesClass(RelationSide::class)]
#[UsesClass(PlanSyntaxException::class)]
#[UsesClass(PlanUndefinedException::class)]
final class FixturePlanTest extends TestCase
{
    #[Test]
    public function fromReadsTheRelationString(): void
    {
        $plan = FixturePlan::from('order.id < order_detail.order_id');

        self::assertCount(1, $plan->relations);
        self::assertSame(['order', 'order_detail'], $plan->tables);
    }

    #[Test]
    public function fromAcceptsAPlanAndCopiesIt(): void
    {
        $original = FixturePlan::from('order.id < order_detail.order_id');
        $copy = FixturePlan::from($original);

        self::assertNotSame($original, $copy);
        self::assertSame($original->toString(), $copy->toString());
    }

    #[Test]
    public function toStringWritesThePlanBack(): void
    {
        $plan = FixturePlan::from('order.id<order_detail.order_id');

        self::assertSame('order.id < order_detail.order_id', $plan->toString());
    }

    #[Test]
    public function castingToStringWritesThePlanBack(): void
    {
        self::assertSame(
            'order.id < order_detail.order_id',
            (string) FixturePlan::from('order.id < order_detail.order_id')
        );
    }

    #[Test]
    public function tableStartsAPlanWithNoRelations(): void
    {
        $plan = FixturePlan::table('order');

        self::assertSame([], $plan->relations);
        self::assertSame(['order'], $plan->tables);
        self::assertSame('order', $plan->toString());
    }

    #[Test]
    public function withOneToManyAddsARelation(): void
    {
        $plan = FixturePlan::table('order')->withOneToMany('order.id', 'order_detail.order_id');

        self::assertSame('order.id < order_detail.order_id', $plan->toString());
    }

    #[Test]
    public function withManyToOneAddsARelation(): void
    {
        $plan = FixturePlan::table('order')->withManyToOne('order.customer_id', 'customer.id');

        self::assertSame('order.customer_id > customer.id', $plan->toString());
    }

    #[Test]
    public function withManyToOneCanMarkTheParentOptional(): void
    {
        $plan = FixturePlan::table('order')->withManyToOne('order.customer_id', 'customer.id', true);

        self::assertSame('order.customer_id >? customer.id', $plan->toString());
        self::assertTrue($plan->relations[0]->parentIsOptional());
    }

    #[Test]
    public function withOneToOneAddsARelation(): void
    {
        $plan = FixturePlan::table('order')->withOneToOne('order.id', 'order_shipping.order_id');

        self::assertSame('order.id - order_shipping.order_id', $plan->toString());
    }

    #[Test]
    public function withRelationAcceptsAColumnRefForCompositeKeys(): void
    {
        $plan = FixturePlan::table('order')->withOneToMany(
            ColumnRef::of('order', 'shop_id', 'no'),
            ColumnRef::of('order_detail', 'shop_id', 'order_no')
        );

        self::assertSame('order.(shop_id, no) < order_detail.(shop_id, order_no)', $plan->toString());
    }

    #[Test]
    public function withTableNamesATableThatHasNoRelationYet(): void
    {
        $plan = FixturePlan::table('order')->withTable('audit_log');

        self::assertSame(['order', 'audit_log'], $plan->tables);
    }

    #[Test]
    public function tablesAreNotRepeated(): void
    {
        $plan = FixturePlan::table('order')
            ->withOneToMany('order.id', 'order_detail.order_id')
            ->withOneToMany('order.id', 'shipment.order_id');

        self::assertSame(['order', 'order_detail', 'shipment'], $plan->tables);
    }

    #[Test]
    public function everyBuilderMethodReturnsANewInstance(): void
    {
        $base = FixturePlan::table('order');

        self::assertNotSame($base, $base->withOneToMany('order.id', 'order_detail.order_id'));
        self::assertNotSame($base, $base->withTable('customer'));
        self::assertSame([], $base->relations);
    }

    #[Test]
    public function theRootTableIsTheFirstOneNamed(): void
    {
        $plan = FixturePlan::from('order.id < order_detail.order_id, order.customer_id > customer.id');

        self::assertSame('order', $plan->rootTable());
    }

    #[Test]
    public function anEmptyPlanHasNoRootTable(): void
    {
        self::assertNull((new FixturePlan())->rootTable());
    }

    #[Test]
    public function relationsFromSelectsByParentTable(): void
    {
        $plan = FixturePlan::from(
            'order.id < order_detail.order_id, order_detail.product_id > product.id'
        );

        self::assertCount(1, $plan->relationsFrom('order'));
        self::assertSame('order_detail', $plan->relationsFrom('order')[0]->child()->table);
        self::assertCount(1, $plan->relationsFrom('product'));
        self::assertSame([], $plan->relationsFrom('customer'));
    }

    #[Test]
    public function theStringAndTheObjectDescribeTheSamePlan(): void
    {
        $written = 'order.id < order_detail.order_id, order.customer_id > customer.id';
        $built = FixturePlan::table('order')
            ->withOneToMany('order.id', 'order_detail.order_id')
            ->withManyToOne('order.customer_id', 'customer.id');

        self::assertSame($written, $built->toString());
        self::assertSame($written, FixturePlan::from($written)->toString());
    }

    #[Test]
    public function aSubclassDefinesANamedPlan(): void
    {
        $plan = OrderWithDetailsPlan::define();

        self::assertInstanceOf(OrderWithDetailsPlan::class, $plan);
        self::assertSame(
            'order.id < order_detail.order_id, order.customer_id > customer.id',
            $plan->toString()
        );
    }

    #[Test]
    public function aSubclassWithoutADefinitionSaysSo(): void
    {
        $this->expectException(PlanUndefinedException::class);
        $this->expectExceptionMessage('Override definition()');

        FixturePlan::define();
    }

    #[Test]
    public function aFluentCallOnASubclassKeepsItsType(): void
    {
        $plan = OrderWithDetailsPlan::define()->withTable('audit_log');

        self::assertInstanceOf(OrderWithDetailsPlan::class, $plan);
    }

    #[Test]
    public function anEndpointWithoutAColumnIsRejected(): void
    {
        $this->expectException(PlanSyntaxException::class);
        $this->expectExceptionMessage("'.' after the table name");

        FixturePlan::table('order')->withOneToMany('order', 'order_detail.order_id');
    }
}
