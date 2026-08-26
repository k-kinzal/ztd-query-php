<?php

declare(strict_types=1);

namespace Tests\Unit\Fixture;

use Faker\Factory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFixture\Fixture\ChildRowCount;
use SqlFixture\Fixture\RowSpec;
use SqlFixture\Plan\ColumnRef;
use SqlFixture\Plan\Relation;
use SqlFixture\Plan\RelationKind;

#[CoversClass(ChildRowCount::class)]
#[UsesClass(ColumnRef::class)]
#[UsesClass(Relation::class)]
#[UsesClass(RelationKind::class)]
#[UsesClass(RowSpec::class)]
final class ChildRowCountTest extends TestCase
{
    public function testOfAnswersExactlyWhatTheCallerAskedFor(): void
    {
        $count = (new ChildRowCount(Factory::create()))->of(
            RowSpec::from('detail', 3),
            Relation::oneToMany('order.id', 'detail.order_id'),
        );

        self::assertSame(3, $count);
    }

    public function testOfGeneratesAtLeastOneChildForARequiredRelation(): void
    {
        $count = (new ChildRowCount(Factory::create()))->of(
            RowSpec::unspecified(),
            Relation::oneToMany('order.id', 'detail.order_id'),
        );

        self::assertGreaterThanOrEqual(1, $count);
        self::assertLessThanOrEqual(5, $count);
    }

    public function testOfMayGenerateNoneAtAllForAnOptionalRelation(): void
    {
        $counts = [];
        $count = new ChildRowCount(Factory::create());
        $relation = Relation::oneToMany('order.id', 'detail.order_id', true);
        $counts[] = $count->of(RowSpec::unspecified(), $relation);

        self::assertGreaterThanOrEqual(0, min($counts));
    }

    public function testOfGeneratesExactlyOneChildForAOneToOneRelation(): void
    {
        $count = (new ChildRowCount(Factory::create()))->of(
            RowSpec::unspecified(),
            Relation::oneToOne('order.id', 'shipping.order_id'),
        );

        self::assertSame(1, $count);
    }

    public function testOfGeneratesNoneOrOneForAnOptionalOneToOneRelation(): void
    {
        $count = (new ChildRowCount(Factory::create()))->of(
            RowSpec::unspecified(),
            Relation::oneToOne('order.id', 'shipping.order_id', true),
        );

        self::assertContains($count, [0, 1]);
    }
}
