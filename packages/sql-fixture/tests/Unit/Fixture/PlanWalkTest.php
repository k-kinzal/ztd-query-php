<?php

declare(strict_types=1);

namespace Tests\Unit\Fixture;

use Faker\Factory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFixture\Fixture\ChildRowCount;
use SqlFixture\Fixture\GenerationRun;
use SqlFixture\Fixture\PlanSchemaException;
use SqlFixture\Fixture\PlanWalk;
use SqlFixture\Fixture\RowSpec;
use SqlFixture\FixtureGenerator;
use SqlFixture\Plan\ColumnRef;
use SqlFixture\Plan\FixturePlan;
use SqlFixture\Plan\PlanParser;
use SqlFixture\Plan\Relation;
use SqlFixture\Plan\RelationKind;
use SqlFixture\Plan\RelationSide;
use SqlFixture\Platform\MySql\MySqlSchemaParser;
use SqlFixture\Platform\MySql\MySqlTypeMapper;
use SqlFixture\Schema\ColumnDefinition;
use SqlFixture\Schema\SchemaNotFoundException;
use SqlFixture\Schema\StaticSchemaResolver;
use SqlFixture\Schema\TableSchema;
use Tests\Fixture\Fixture\ShopSchemas;

#[CoversClass(PlanWalk::class)]
#[UsesClass(GenerationRun::class)]
#[UsesClass(RowSpec::class)]
#[UsesClass(ChildRowCount::class)]
#[UsesClass(FixtureGenerator::class)]
#[UsesClass(FixturePlan::class)]
#[UsesClass(PlanParser::class)]
#[UsesClass(Relation::class)]
#[UsesClass(ColumnRef::class)]
#[UsesClass(RelationKind::class)]
#[UsesClass(RelationSide::class)]
#[UsesClass(PlanSchemaException::class)]
#[UsesClass(MySqlSchemaParser::class)]
#[UsesClass(MySqlTypeMapper::class)]
#[UsesClass(StaticSchemaResolver::class)]
#[UsesClass(SchemaNotFoundException::class)]
#[UsesClass(TableSchema::class)]
#[UsesClass(ColumnDefinition::class)]
final class PlanWalkTest extends TestCase
{
    #[Test]
    public function testMaterializeGeneratesAsManyRowsAsItWasAskedFor(): void
    {
        $plan = FixturePlan::table('order');
        $run = new GenerationRun([]);
        $walk = new PlanWalk(
            $plan,
            $run,
            ShopSchemas::resolver(),
            new FixtureGenerator(Factory::create()),
            new ChildRowCount(Factory::create())
        );

        $walk->materialize('order', [], 3, true, null);

        self::assertCount(3, $run->toSet($plan)->rows('order'));
    }

    #[Test]
    public function testMaterializeFollowsARelationOutToTheChildTable(): void
    {
        $plan = FixturePlan::from('order.id < order_detail.order_id');
        $run = new GenerationRun(RowSpec::forTables(['order_detail' => 2]));
        $walk = new PlanWalk(
            $plan,
            $run,
            ShopSchemas::resolver(),
            new FixtureGenerator(Factory::create()),
            new ChildRowCount(Factory::create())
        );

        $walk->materialize('order', [], 1, false, null);

        $set = $run->toSet($plan);
        self::assertCount(1, $set->rows('order'));
        self::assertCount(2, $set->rows('order_detail'));
    }

    #[Test]
    public function testMaterializeRefusesATableNothingCanResolve(): void
    {
        $plan = FixturePlan::table('order');
        $walk = new PlanWalk(
            $plan,
            new GenerationRun([]),
            new StaticSchemaResolver(),
            new FixtureGenerator(Factory::create()),
            new ChildRowCount(Factory::create())
        );

        $this->expectException(SchemaNotFoundException::class);

        $walk->materialize('order', [], 1, false, null);
    }

    #[Test]
    public function testRowKeepsTheColumnsTheCallerFixedOnThatRow(): void
    {
        $plan = FixturePlan::table('order');
        $run = new GenerationRun(RowSpec::forTables(['order' => ['status' => 'paid']]));
        $walk = new PlanWalk(
            $plan,
            $run,
            ShopSchemas::resolver(),
            new FixtureGenerator(Factory::create()),
            new ChildRowCount(Factory::create())
        );

        $walk->row(ShopSchemas::resolver()->resolve('order'), [], $run->specFor('order'), 0, false, null);

        self::assertSame('paid', $run->lastRow('order')['status']);
    }

    #[Test]
    public function testRowCarriesTheColumnsTheRelationWalkedInOnAlreadyFixed(): void
    {
        $plan = FixturePlan::from('order.id < order_detail.order_id');
        $run = new GenerationRun([]);
        $walk = new PlanWalk(
            $plan,
            $run,
            ShopSchemas::resolver(),
            new FixtureGenerator(Factory::create()),
            new ChildRowCount(Factory::create())
        );

        $walk->row(
            ShopSchemas::resolver()->resolve('order_detail'),
            ['order_id' => 42],
            RowSpec::unspecified(),
            0,
            false,
            $plan->relations[0]
        );

        self::assertSame(42, $run->lastRow('order_detail')['order_id']);
    }

    #[Test]
    public function testLinkToParentGeneratesTheParentAndReadsItsKeyOffIt(): void
    {
        $plan = FixturePlan::from('order.id < order_detail.order_id');
        $run = new GenerationRun([]);
        $walk = new PlanWalk(
            $plan,
            $run,
            ShopSchemas::resolver(),
            new FixtureGenerator(Factory::create()),
            new ChildRowCount(Factory::create())
        );

        $linked = $walk->linkToParent($plan->relations[0], [], false);

        self::assertSame(['order_id' => $run->lastRow('order')['id']], $linked);
    }

    #[Test]
    public function testLinkToParentGeneratesNothingWhereTheCallerAlreadySaidWhatIsReferenced(): void
    {
        $plan = FixturePlan::from('order.id < order_detail.order_id');
        $run = new GenerationRun([]);
        $walk = new PlanWalk(
            $plan,
            $run,
            ShopSchemas::resolver(),
            new FixtureGenerator(Factory::create()),
            new ChildRowCount(Factory::create())
        );

        $linked = $walk->linkToParent($plan->relations[0], ['order_id' => 7], false);

        self::assertSame([], $linked);
        self::assertSame([], $run->lastRow('order'));
    }

    #[Test]
    public function testLinkToParentLeavesAnOptionalParentNobodyAskedForUngenerated(): void
    {
        $plan = FixturePlan::from('order.id ?< order_detail.order_id');
        $run = new GenerationRun([]);
        $walk = new PlanWalk(
            $plan,
            $run,
            ShopSchemas::resolver(),
            new FixtureGenerator(Factory::create()),
            new ChildRowCount(Factory::create())
        );

        self::assertSame([], $walk->linkToParent($plan->relations[0], [], false));
        self::assertSame([], $run->lastRow('order'));
    }

    #[Test]
    public function testLinkToChildrenPointsEveryChildRowAtTheRowItWasGiven(): void
    {
        $plan = FixturePlan::from('order.id < order_detail.order_id');
        $run = new GenerationRun(RowSpec::forTables(['order_detail' => 3]));
        $walk = new PlanWalk(
            $plan,
            $run,
            ShopSchemas::resolver(),
            new FixtureGenerator(Factory::create()),
            new ChildRowCount(Factory::create())
        );

        $walk->linkToChildren($plan->relations[0], ['id' => 9], false);

        $rows = $run->toSet($plan)->rows('order_detail');

        self::assertCount(3, $rows);
        self::assertSame([9, 9, 9], array_column($rows, 'order_id'));
    }

    #[Test]
    public function testLinkToChildrenRefusesARowThatDoesNotCarryALinkingColumn(): void
    {
        $plan = FixturePlan::from('order.id < order_detail.order_id');
        $walk = new PlanWalk(
            $plan,
            new GenerationRun([]),
            ShopSchemas::resolver(),
            new FixtureGenerator(Factory::create()),
            new ChildRowCount(Factory::create())
        );

        $this->expectException(PlanSchemaException::class);

        $walk->linkToChildren($plan->relations[0], ['status' => 'paid'], false);
    }
}
