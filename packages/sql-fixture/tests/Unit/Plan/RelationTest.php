<?php

declare(strict_types=1);

namespace Tests\Unit\Plan;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFixture\Plan\ColumnRef;
use SqlFixture\Plan\PlanSyntaxException;
use SqlFixture\Plan\Relation;
use SqlFixture\Plan\RelationKind;
use SqlFixture\Plan\RelationSide;

#[CoversClass(Relation::class)]
#[UsesClass(ColumnRef::class)]
#[UsesClass(RelationKind::class)]
#[UsesClass(RelationSide::class)]
#[UsesClass(PlanSyntaxException::class)]
final class RelationTest extends TestCase
{
    #[Test]
    public function keepsTheSidesInTheOrderTheyWereWritten(): void
    {
        $relation = new Relation(
            ColumnRef::of('order', 'id'),
            RelationKind::OneToMany,
            ColumnRef::of('order_detail', 'order_id')
        );

        self::assertSame('order', $relation->left->table);
        self::assertSame('order_detail', $relation->right->table);
    }

    #[Test]
    public function oneToManyMakesTheLeftSideTheParent(): void
    {
        $relation = new Relation(
            ColumnRef::of('order', 'id'),
            RelationKind::OneToMany,
            ColumnRef::of('order_detail', 'order_id')
        );

        self::assertSame('order', $relation->parent()->table);
        self::assertSame('order_detail', $relation->child()->table);
    }

    #[Test]
    public function manyToOneDescribesTheSameShapeWrittenBackwards(): void
    {
        $relation = new Relation(
            ColumnRef::of('order_detail', 'order_id'),
            RelationKind::ManyToOne,
            ColumnRef::of('order', 'id')
        );

        self::assertSame('order', $relation->parent()->table);
        self::assertSame('order_detail', $relation->child()->table);
    }

    #[Test]
    public function columnMapPointsChildColumnsAtParentColumns(): void
    {
        $relation = new Relation(
            ColumnRef::of('order', 'id'),
            RelationKind::OneToMany,
            ColumnRef::of('order_detail', 'order_id')
        );

        self::assertSame(['order_id' => 'id'], $relation->columnMap());
    }

    #[Test]
    public function columnMapPairsCompositeKeysPositionally(): void
    {
        $relation = new Relation(
            ColumnRef::of('order', 'shop_id', 'no'),
            RelationKind::OneToMany,
            ColumnRef::of('order_detail', 'shop_id', 'order_no')
        );

        self::assertSame(['shop_id' => 'shop_id', 'order_no' => 'no'], $relation->columnMap());
    }

    #[Test]
    public function columnMapReadsTheSameWrittenEitherWay(): void
    {
        $forwards = new Relation(
            ColumnRef::of('order', 'id'),
            RelationKind::OneToMany,
            ColumnRef::of('order_detail', 'order_id')
        );
        $backwards = new Relation(
            ColumnRef::of('order_detail', 'order_id'),
            RelationKind::ManyToOne,
            ColumnRef::of('order', 'id')
        );

        self::assertSame($forwards->columnMap(), $backwards->columnMap());
    }

    #[Test]
    public function aMarkerNextToTheParentMakesTheParentOptional(): void
    {
        $relation = new Relation(
            ColumnRef::of('order_detail', 'order_id'),
            RelationKind::ManyToOne,
            ColumnRef::of('order', 'id'),
            false,
            true
        );

        self::assertTrue($relation->parentIsOptional());
        self::assertFalse($relation->childIsOptional());
    }

    #[Test]
    public function aMarkerNextToTheChildMakesTheChildOptional(): void
    {
        $relation = new Relation(
            ColumnRef::of('order', 'id'),
            RelationKind::OneToMany,
            ColumnRef::of('order_detail', 'order_id'),
            false,
            true
        );

        self::assertTrue($relation->childIsOptional());
        self::assertFalse($relation->parentIsOptional());
    }

    #[Test]
    public function bothSidesAreRequiredByDefault(): void
    {
        $relation = new Relation(
            ColumnRef::of('order', 'id'),
            RelationKind::OneToMany,
            ColumnRef::of('order_detail', 'order_id')
        );

        self::assertFalse($relation->parentIsOptional());
        self::assertFalse($relation->childIsOptional());
    }

    #[Test]
    public function onlyOneToOneHoldsASingleChildRow(): void
    {
        $many = new Relation(
            ColumnRef::of('order', 'id'),
            RelationKind::OneToMany,
            ColumnRef::of('order_detail', 'order_id')
        );
        $one = new Relation(
            ColumnRef::of('order', 'id'),
            RelationKind::OneToOne,
            ColumnRef::of('order_shipping', 'order_id')
        );

        self::assertTrue($many->childIsCollection());
        self::assertFalse($one->childIsCollection());
    }

    #[Test]
    public function tablesListsBothEnds(): void
    {
        $relation = new Relation(
            ColumnRef::of('order', 'id'),
            RelationKind::OneToMany,
            ColumnRef::of('order_detail', 'order_id')
        );

        self::assertSame(['order', 'order_detail'], $relation->tables());
    }

    #[Test]
    public function aSelfReferenceNamesItsTableOnce(): void
    {
        $relation = new Relation(
            ColumnRef::of('category', 'id'),
            RelationKind::OneToMany,
            ColumnRef::of('category', 'parent_id')
        );

        self::assertSame(['category'], $relation->tables());
    }

    #[Test]
    public function sidesOfDifferentArityAreRejected(): void
    {
        $this->expectException(PlanSyntaxException::class);
        $this->expectExceptionMessage('names 2 columns on one side and 1 on the other');

        new Relation(
            ColumnRef::of('order', 'shop_id', 'no'),
            RelationKind::OneToMany,
            ColumnRef::of('order_detail', 'order_no')
        );
    }

    #[Test]
    public function namedConstructorsBuildEachOperator(): void
    {
        self::assertSame(RelationKind::OneToMany, Relation::oneToMany('a.id', 'b.a_id')->kind);
        self::assertSame(RelationKind::ManyToOne, Relation::manyToOne('b.a_id', 'a.id')->kind);
        self::assertSame(RelationKind::OneToOne, Relation::oneToOne('a.id', 'b.a_id')->kind);
    }

    #[Test]
    public function namedConstructorsReadEndpointsWrittenAsStrings(): void
    {
        $relation = Relation::oneToMany('order.(shop_id, no)', 'order_detail.(shop_id, order_no)');

        self::assertSame(['shop_id', 'no'], $relation->left->columns);
        self::assertSame(['shop_id', 'order_no'], $relation->right->columns);
    }

    #[Test]
    public function namedConstructorsAlsoTakeColumnRefs(): void
    {
        $relation = Relation::oneToMany(ColumnRef::of('order', 'id'), ColumnRef::of('order_detail', 'order_id'));

        self::assertSame('order.id', $relation->left->toString());
    }

    #[Test]
    public function oneToManyCanMarkTheChildOptional(): void
    {
        self::assertTrue(Relation::oneToMany('a.id', 'b.a_id', true)->childIsOptional());
        self::assertFalse(Relation::oneToMany('a.id', 'b.a_id')->childIsOptional());
    }

    #[Test]
    public function manyToOneCanMarkTheParentOptional(): void
    {
        self::assertTrue(Relation::manyToOne('b.a_id', 'a.id', true)->parentIsOptional());
        self::assertFalse(Relation::manyToOne('b.a_id', 'a.id')->parentIsOptional());
    }

    #[Test]
    public function aRequiredChildIsGeneratedEvenWhenNoneAreGiven(): void
    {
        self::assertSame(1, Relation::oneToMany('order.id', 'order_detail.order_id')->minimumChildRows());
    }

    #[Test]
    public function anOptionalChildMayEndUpWithNoRows(): void
    {
        self::assertSame(0, Relation::oneToMany('order.id', 'order_detail.order_id', true)->minimumChildRows());
    }

    #[Test]
    public function aCollectionChildHasNoUpperBound(): void
    {
        self::assertNull(Relation::oneToMany('order.id', 'order_detail.order_id')->maximumChildRows());
    }

    #[Test]
    public function aOneToOneChildIsCappedAtOneRow(): void
    {
        self::assertSame(1, Relation::oneToOne('order.id', 'order_shipping.order_id')->maximumChildRows());
    }

    #[Test]
    public function anOptionalOneToOneChildRangesFromNoneToOne(): void
    {
        $relation = Relation::oneToOne('order.id', 'order_shipping.order_id', true);

        self::assertSame(0, $relation->minimumChildRows());
        self::assertSame(1, $relation->maximumChildRows());
    }

    #[Test]
    public function namedConstructorsLeaveTheLeftSideRequired(): void
    {
        self::assertFalse(Relation::oneToMany('a.id', 'b.a_id', true)->leftOptional);
        self::assertFalse(Relation::manyToOne('b.a_id', 'a.id', true)->leftOptional);
        self::assertFalse(Relation::oneToOne('a.id', 'b.a_id', true)->leftOptional);
    }

    #[Test]
    public function oneToManyMarksOnlyTheRightSideOptional(): void
    {
        $relation = Relation::oneToMany('a.id', 'b.a_id', true);

        self::assertFalse($relation->leftOptional);
        self::assertTrue($relation->rightOptional);
    }

    #[Test]
    public function oneToOneMarksOnlyTheRightSideOptional(): void
    {
        $relation = Relation::oneToOne('a.id', 'b.a_id', true);

        self::assertFalse($relation->leftOptional);
        self::assertTrue($relation->rightOptional);
    }

    #[Test]
    public function manyToOneMarksOnlyTheRightSideOptional(): void
    {
        $relation = Relation::manyToOne('b.a_id', 'a.id', true);

        self::assertFalse($relation->leftOptional);
        self::assertTrue($relation->rightOptional);
    }

    #[Test]
    public function oneToManyLeavesBothSidesRequiredByDefault(): void
    {
        $relation = Relation::oneToMany('a.id', 'b.a_id');

        self::assertFalse($relation->leftOptional);
        self::assertFalse($relation->rightOptional);
    }

    #[Test]
    public function manyToOneLeavesBothSidesRequiredByDefault(): void
    {
        $relation = Relation::manyToOne('b.a_id', 'a.id');

        self::assertFalse($relation->leftOptional);
        self::assertFalse($relation->rightOptional);
    }

    #[Test]
    public function oneToOneLeavesBothSidesRequiredByDefault(): void
    {
        $relation = Relation::oneToOne('a.id', 'b.a_id');

        self::assertFalse($relation->leftOptional);
        self::assertFalse($relation->rightOptional);
    }
}
