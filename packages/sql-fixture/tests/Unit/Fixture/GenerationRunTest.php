<?php

declare(strict_types=1);

namespace Tests\Unit\Fixture;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFixture\Fixture\GenerationRun;
use SqlFixture\Fixture\RowSpec;
use SqlFixture\Plan\FixturePlan;
use SqlFixture\Schema\ColumnDefinition;
use SqlFixture\Schema\TableSchema;
use Tests\Fixture\Fixture\OrderSchema;

#[CoversClass(GenerationRun::class)]
#[UsesClass(RowSpec::class)]
#[UsesClass(FixturePlan::class)]
#[UsesClass(\SqlFixture\Fixture\FixtureSet::class)]
#[UsesClass(TableSchema::class)]
#[UsesClass(ColumnDefinition::class)]
#[UsesClass(\SqlFixture\Fixture\OverrideRows::class)]
#[UsesClass(\SqlFixture\Fixture\TableOverrides::class)]
#[UsesClass(\SqlFixture\Plan\ColumnRef::class)]
#[UsesClass(\SqlFixture\Plan\PlanParser::class)]
#[UsesClass(\SqlFixture\Plan\PlanPrinter::class)]
#[UsesClass(\SqlFixture\Plan\PlanSyntaxException::class)]
#[UsesClass(\SqlFixture\Plan\Relation::class)]
#[UsesClass(\SqlFixture\Plan\RelationKind::class)]
#[UsesClass(\SqlFixture\Plan\RelationSide::class)]
#[UsesClass(\SqlFixture\Plan\Parsing\PlanStatements::class)]
#[UsesClass(\SqlFixture\Plan\Parsing\RelationCursor::class)]
#[UsesClass(\SqlFixture\Plan\Parsing\RelationReader::class)]
#[UsesClass(\SqlFixture\Plan\PlanStructureException::class)]
#[UsesClass(\SqlFixture\Plan\Printing\PlanTables::class)]
#[UsesClass(\SqlFixture\Plan\Printing\RelationGroups::class)]
#[UsesClass(\SqlFixture\Plan\Printing\StatementPrinter::class)]
#[UsesClass(\SqlFixture\Plan\Validation\PlanValidation::class)]
#[UsesClass(\SqlFixture\Plan\Validation\TableName::class)]
final class GenerationRunTest extends TestCase
{
    #[Test]
    public function testClaimingATableStopsASecondWalkStarting(): void
    {
        $run = new GenerationRun([]);
        $run->claim(['order', 'customer']);

        self::assertTrue($run->hasVisited('order'));
        self::assertTrue($run->hasVisited('customer'));
        self::assertFalse($run->hasVisited('product'));
    }

    #[Test]
    public function testReachedReachingATableAsAListSticks(): void
    {
        $run = new GenerationRun([]);
        $run->claim(['order_detail']);
        $run->reached('order_detail', true);
        $run->reached('order_detail', false);

        $plan = FixturePlan::table('order_detail');
        self::assertSame([], $run->toSet($plan)['order_detail']);
    }

    #[Test]
    public function testToSetATableReachedOnlyAsASingleRowReadsBackAsOne(): void
    {
        $run = new GenerationRun([]);
        $run->reached('order', false);
        $run->record(OrderSchema::create(), ['status' => 'paid']);

        self::assertSame(
            ['status' => 'paid'],
            $run->toSet(FixturePlan::table('order'))['order']
        );
    }

    #[Test]
    public function testAKeyNothingReadsIsLeftToTheDatabase(): void
    {
        $run = new GenerationRun([]);

        self::assertArrayNotHasKey('id', $run->record(OrderSchema::create(), ['status' => 'paid']));
    }

    #[Test]
    public function testAKeyARelationReadsIsStoodInFor(): void
    {
        $run = new GenerationRun([]);

        self::assertSame(1, $run->record(OrderSchema::create(), [], ['id'])['id']);
        self::assertSame(2, $run->record(OrderSchema::create(), [], ['id'])['id']);
    }

    #[Test]
    public function testRecordKeepsAKeyTheCallerSupplied(): void
    {
        $run = new GenerationRun([]);

        self::assertSame(100, $run->record(OrderSchema::create(), ['id' => 100], ['id'])['id']);
    }

    #[Test]
    public function testOnlyAutoIncrementColumnsAreStoodInFor(): void
    {
        $run = new GenerationRun([]);

        self::assertArrayNotHasKey('status', $run->record(OrderSchema::create(), [], ['status']));
    }

    #[Test]
    public function testWasAskedForReportsWhatTheCallerMentioned(): void
    {
        $run = new GenerationRun(['order' => RowSpec::from('order', 2)]);

        self::assertTrue($run->wasAskedFor('order'));
        self::assertFalse($run->wasAskedFor('customer'));
    }

    #[Test]
    public function testSpecForFallsBackToUnspecified(): void
    {
        $run = new GenerationRun(['order' => RowSpec::from('order', 2)]);

        self::assertSame(2, $run->specFor('order')->count);
        self::assertNull($run->specFor('customer')->count);
    }

    #[Test]
    public function testATableThatGeneratedNothingReadsBackAsNull(): void
    {
        $run = new GenerationRun([]);

        self::assertNull($run->toSet(FixturePlan::table('order'))['order']);
    }

    #[Test]
    public function testClaimingDoesNotUndoWhatTheWalkAlreadyLearnt(): void
    {
        $run = new GenerationRun([]);
        $run->reached('order', true);
        $run->claim(['order']);
        $run->record(OrderSchema::create(), ['status' => 'paid']);

        self::assertSame(
            [['status' => 'paid']],
            $run->toSet(FixturePlan::table('order'))['order']
        );
    }

    #[Test]
    public function testAClaimedTableThatWasNeverReachedIsNotAList(): void
    {
        $run = new GenerationRun([]);
        $run->claim(['order']);

        self::assertNull($run->toSet(FixturePlan::table('order'))['order']);
    }

    #[Test]
    public function testEveryReferencedColumnIsConsideredNotJustTheFirst(): void
    {
        $run = new GenerationRun([]);

        $row = $run->record(OrderSchema::create(), [], ['status', 'id']);

        self::assertSame(1, $row['id']);
    }

    #[Test]
    public function testRecordReturnsTheWholeRow(): void
    {
        $run = new GenerationRun([]);

        self::assertSame(
            ['status' => 'paid', 'id' => 1],
            $run->record(OrderSchema::create(), ['status' => 'paid'], ['id'])
        );
    }
    public function testLastRowReturnsTheMostRecentRecordedValues(): void
    {
        $run = new GenerationRun([]);
        $run->record(OrderSchema::create(), ['status' => 'new']);
        $run->record(OrderSchema::create(), ['status' => 'paid']);
        self::assertSame(['status' => 'paid'], $run->lastRow('order'));
        self::assertSame([], $run->lastRow('unknown'));
    }
    public function testHasVisitedDistinguishesNewAndClaimedTables(): void
    {
        $run = new GenerationRun([]);
        self::assertFalse($run->hasVisited('order'));
        $run->claim(['order']);
        self::assertTrue($run->hasVisited('order'));
    }
}
