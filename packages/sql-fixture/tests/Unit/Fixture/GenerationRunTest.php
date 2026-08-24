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
final class GenerationRunTest extends TestCase
{
    #[Test]
    public function claimingATableStopsASecondWalkStarting(): void
    {
        $run = new GenerationRun([]);
        $run->claim(['order', 'customer']);

        self::assertTrue($run->hasVisited('order'));
        self::assertTrue($run->hasVisited('customer'));
        self::assertFalse($run->hasVisited('product'));
    }

    #[Test]
    public function reachingATableAsAListSticks(): void
    {
        $run = new GenerationRun([]);
        $run->claim(['order_detail']);
        $run->reached('order_detail', true);
        $run->reached('order_detail', false);

        $plan = FixturePlan::table('order_detail');
        self::assertSame([], $run->toSet($plan)['order_detail']);
    }

    #[Test]
    public function aTableReachedOnlyAsASingleRowReadsBackAsOne(): void
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
    public function aKeyNothingReadsIsLeftToTheDatabase(): void
    {
        $run = new GenerationRun([]);

        self::assertArrayNotHasKey('id', $run->record(OrderSchema::create(), ['status' => 'paid']));
    }

    #[Test]
    public function aKeyARelationReadsIsStoodInFor(): void
    {
        $run = new GenerationRun([]);

        self::assertSame(1, $run->record(OrderSchema::create(), [], ['id'])['id']);
        self::assertSame(2, $run->record(OrderSchema::create(), [], ['id'])['id']);
    }

    #[Test]
    public function recordKeepsAKeyTheCallerSupplied(): void
    {
        $run = new GenerationRun([]);

        self::assertSame(100, $run->record(OrderSchema::create(), ['id' => 100], ['id'])['id']);
    }

    #[Test]
    public function onlyAutoIncrementColumnsAreStoodInFor(): void
    {
        $run = new GenerationRun([]);

        self::assertArrayNotHasKey('status', $run->record(OrderSchema::create(), [], ['status']));
    }

    #[Test]
    public function wasAskedForReportsWhatTheCallerMentioned(): void
    {
        $run = new GenerationRun(['order' => RowSpec::from('order', 2)]);

        self::assertTrue($run->wasAskedFor('order'));
        self::assertFalse($run->wasAskedFor('customer'));
    }

    #[Test]
    public function specForFallsBackToUnspecified(): void
    {
        $run = new GenerationRun(['order' => RowSpec::from('order', 2)]);

        self::assertSame(2, $run->specFor('order')->count);
        self::assertNull($run->specFor('customer')->count);
    }

    #[Test]
    public function aTableThatGeneratedNothingReadsBackAsNull(): void
    {
        $run = new GenerationRun([]);

        self::assertNull($run->toSet(FixturePlan::table('order'))['order']);
    }

    #[Test]
    public function claimingDoesNotUndoWhatTheWalkAlreadyLearnt(): void
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
    public function aClaimedTableThatWasNeverReachedIsNotAList(): void
    {
        $run = new GenerationRun([]);
        $run->claim(['order']);

        self::assertNull($run->toSet(FixturePlan::table('order'))['order']);
    }

    #[Test]
    public function everyReferencedColumnIsConsideredNotJustTheFirst(): void
    {
        $run = new GenerationRun([]);

        $row = $run->record(OrderSchema::create(), [], ['status', 'id']);

        self::assertSame(1, $row['id']);
    }

    #[Test]
    public function recordReturnsTheWholeRow(): void
    {
        $run = new GenerationRun([]);

        self::assertSame(
            ['status' => 'paid', 'id' => 1],
            $run->record(OrderSchema::create(), ['status' => 'paid'], ['id'])
        );
    }
}
