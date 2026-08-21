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
}
