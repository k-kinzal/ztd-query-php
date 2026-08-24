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
use SqlFixture\Plan\PlanStructureException;
use SqlFixture\Plan\PlanSyntaxException;
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
#[UsesClass(PlanStructureException::class)]
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
    public function theSubjectIsTheFirstTableNamed(): void
    {
        $plan = FixturePlan::from('order.id < order_detail.order_id, order.customer_id > customer.id');

        self::assertSame('order', $plan->subjectTable());
    }

    #[Test]
    public function anEmptyPlanHasNoSubject(): void
    {
        self::assertNull((new FixturePlan())->subjectTable());
    }

    #[Test]
    public function theSubjectIsNotNecessarilyGeneratedFirst(): void
    {
        $plan = FixturePlan::from('order_detail.order_id > order.id');

        self::assertSame('order_detail', $plan->subjectTable());
        self::assertSame(['order', 'order_detail'], $plan->generationOrder);
    }

    #[Test]
    public function generationOrderPutsEveryParentBeforeItsChildren(): void
    {
        $plan = FixturePlan::from(
            'order.id < order_detail.order_id, order_detail.product_id > product.id'
        );

        self::assertSame(['order', 'product', 'order_detail'], $plan->generationOrder);
    }

    #[Test]
    public function generationOrderCoversTablesThatStandAlone(): void
    {
        $plan = FixturePlan::from('order.id < order_detail.order_id, audit_log');

        self::assertContains('audit_log', $plan->generationOrder);
        self::assertCount(3, $plan->generationOrder);
    }

    #[Test]
    public function generationOrderHandlesSeveralIndependentComponents(): void
    {
        $plan = FixturePlan::from('a.id < b.a_id, c.id < d.c_id');

        self::assertSame(['a', 'c', 'b', 'd'], $plan->generationOrder);
    }

    #[Test]
    public function rootsAreTheTablesNothingHasToPrecede(): void
    {
        $plan = FixturePlan::from('b.a_id > a.id, b.c_id > c.id');

        self::assertSame(['a', 'c'], $plan->roots());
    }

    #[Test]
    public function dependenciesOfSelectsRelationsWhereTheTableIsTheChild(): void
    {
        $plan = FixturePlan::from(
            'order.id < order_detail.order_id, order_detail.product_id > product.id'
        );

        self::assertCount(2, $plan->dependenciesOf('order_detail'));
        self::assertSame([], $plan->dependenciesOf('order'));
        self::assertSame([], $plan->dependenciesOf('product'));
    }

    #[Test]
    public function dependentsOfSelectsRelationsWhereTheTableIsTheParent(): void
    {
        $plan = FixturePlan::from(
            'order.id < order_detail.order_id, order_detail.product_id > product.id'
        );

        self::assertCount(1, $plan->dependentsOf('order'));
        self::assertSame('order_detail', $plan->dependentsOf('order')[0]->child()->table);
        self::assertCount(1, $plan->dependentsOf('product'));
        self::assertSame([], $plan->dependentsOf('order_detail'));
    }

    #[Test]
    public function aCycleIsRejected(): void
    {
        $this->expectException(PlanStructureException::class);
        $this->expectExceptionMessage('form a cycle: a -> b -> a');

        FixturePlan::from('a.id < b.a_id, b.id < a.b_id');
    }

    #[Test]
    public function aRequiredSelfReferenceIsRejected(): void
    {
        $this->expectException(PlanStructureException::class);
        $this->expectExceptionMessage('without end');

        FixturePlan::from('category.id < category.parent_id');
    }

    #[Test]
    public function anOptionalSelfReferenceIsAllowed(): void
    {
        $plan = FixturePlan::from('category.id <? category.parent_id');

        self::assertSame(['category'], $plan->generationOrder);
    }

    #[Test]
    public function bindingTheSameColumnsTwiceIsRejected(): void
    {
        $this->expectException(PlanStructureException::class);
        $this->expectExceptionMessage('b.a_id is bound to a.id and to a.id');

        FixturePlan::from('a.id < b.a_id, a.id < b.a_id');
    }

    #[Test]
    public function bindingAColumnToTwoParentsIsRejected(): void
    {
        $this->expectException(PlanStructureException::class);
        $this->expectExceptionMessage('can reference one parent');

        FixturePlan::from('a.id < b.x, c.id < b.x');
    }

    #[Test]
    public function twoForeignKeysBetweenTheSameTablesAreAllowed(): void
    {
        $plan = FixturePlan::from('a.id < b.a_id, a.code < b.a_code');

        self::assertCount(2, $plan->relations);
        self::assertSame(['a', 'b'], $plan->generationOrder);
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
    public function aPlanCanBeDeclaredAsAType(): void
    {
        $plan = new OrderWithDetailsPlan();

        self::assertInstanceOf(FixturePlan::class, $plan);
        self::assertSame(['order', 'order_detail', 'customer'], $plan->tables);
    }

    #[Test]
    public function aDeclaredPlanWritesOutAsTheSameRelationString(): void
    {
        self::assertSame(
            'order.id < order_detail.order_id, order.customer_id > customer.id',
            (new OrderWithDetailsPlan())->toString()
        );
    }

    #[Test]
    public function aDeclaredPlanEqualsTheParsedString(): void
    {
        $declared = new OrderWithDetailsPlan();
        $parsed = FixturePlan::from('order.id < order_detail.order_id, order.customer_id > customer.id');

        self::assertSame($parsed->toString(), $declared->toString());
        self::assertSame($parsed->tables, $declared->tables);
        self::assertEquals($parsed->relations, $declared->relations);
    }

    #[Test]
    public function constructingFromRelationsNeedsNoString(): void
    {
        $plan = new FixturePlan(
            Relation::oneToMany('order.id', 'order_detail.order_id'),
            Relation::manyToOne('order.customer_id', 'customer.id'),
        );

        self::assertSame(
            'order.id < order_detail.order_id, order.customer_id > customer.id',
            $plan->toString()
        );
    }

    #[Test]
    public function aStringPartNamesAStandaloneTable(): void
    {
        $plan = new FixturePlan(Relation::oneToMany('order.id', 'order_detail.order_id'), 'audit_log');

        self::assertSame(['order', 'order_detail', 'audit_log'], $plan->tables);
    }

    #[Test]
    public function aStringPartHoldingRelationSyntaxIsRejected(): void
    {
        $this->expectException(PlanSyntaxException::class);
        $this->expectExceptionMessage('FixturePlan::from()');

        new FixturePlan('order.id < order_detail.order_id');
    }

    #[Test]
    public function alteringADeclaredPlanGivesAPlainPlan(): void
    {
        $plan = (new OrderWithDetailsPlan())->withTable('audit_log');

        self::assertNotInstanceOf(OrderWithDetailsPlan::class, $plan);
        self::assertSame(['order', 'order_detail', 'customer', 'audit_log'], $plan->tables);
    }

    #[Test]
    public function anEndpointWithoutAColumnIsRejected(): void
    {
        $this->expectException(PlanSyntaxException::class);
        $this->expectExceptionMessage("'.' after the table name");

        FixturePlan::table('order')->withOneToMany('order', 'order_detail.order_id');
    }

    #[Test]
    public function partsSpreadFromAKeyedArrayAreStillReadInOrder(): void
    {
        $plan = new FixturePlan(...[
            'first' => Relation::oneToMany('a.id', 'b.a_id'),
            'second' => 'audit_log',
        ]);

        self::assertSame(['a', 'b', 'audit_log'], $plan->tables);
        self::assertSame('a.id < b.a_id, audit_log', $plan->toString());
    }

    #[Test]
    public function aTableNameIsTakenWithoutSurroundingSpace(): void
    {
        self::assertSame(['order'], (new FixturePlan('  order  '))->tables);
    }

    #[Test]
    public function aQuotedTableNameLosesItsQuotes(): void
    {
        self::assertSame(['order'], (new FixturePlan('`order`'))->tables);
        self::assertSame(['order'], (new FixturePlan('"order"'))->tables);
    }

    #[Test]
    public function dependenciesOfReturnsAListEvenWhenTheMatchIsNotFirst(): void
    {
        $plan = FixturePlan::from('a.id < b.a_id, c.id < d.c_id');

        self::assertSame([$plan->relations[1]], $plan->dependenciesOf('d'));
    }

    #[Test]
    public function dependentsOfReturnsAListEvenWhenTheMatchIsNotFirst(): void
    {
        $plan = FixturePlan::from('a.id < b.a_id, c.id < d.c_id');

        self::assertSame([$plan->relations[1]], $plan->dependentsOf('c'));
    }

    #[Test]
    public function rootsReturnsAListEvenWhenTheFirstTableIsNotOne(): void
    {
        $plan = FixturePlan::from('b.a_id > a.id');

        self::assertSame(['a'], $plan->roots());
    }

    #[Test]
    public function withRelationAddsARelationDirectly(): void
    {
        $plan = FixturePlan::table('order')->withRelation(Relation::oneToMany('order.id', 'order_detail.order_id'));

        self::assertSame('order.id < order_detail.order_id', $plan->toString());
    }

    #[Test]
    public function aPlanBuiltFromAKeyedSpreadCanStillBeExtended(): void
    {
        $plan = (new FixturePlan(...['first' => Relation::oneToMany('a.id', 'b.a_id')]))->withTable('audit_log');

        self::assertSame(['a', 'b', 'audit_log'], $plan->tables);
    }
}
