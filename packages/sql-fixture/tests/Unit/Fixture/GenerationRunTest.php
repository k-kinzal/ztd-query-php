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
#[UsesClass(\SqlFixture\Plan\Exception\EmptyPlanException::class)]
#[UsesClass(\SqlFixture\Plan\Exception\EmptyTableNameException::class)]
#[UsesClass(\SqlFixture\Plan\Exception\MissingEndpointColumnsException::class)]
#[UsesClass(\SqlFixture\Plan\Exception\InvalidTableNameException::class)]
#[UsesClass(\SqlFixture\Plan\Exception\UnbalancedBracketsException::class)]
#[UsesClass(\SqlFixture\Plan\Exception\UnexpectedPlanTokenException::class)]
#[UsesClass(\SqlFixture\Plan\Exception\UnsupportedManyToManyException::class)]
#[UsesClass(\SqlFixture\Plan\Exception\CompositeArityMismatchException::class)]
#[UsesClass(\SqlFixture\Plan\Exception\DuplicateColumnBindingException::class)]
#[UsesClass(\SqlFixture\Plan\Exception\CyclicDependencyException::class)]
#[UsesClass(\SqlFixture\Plan\Exception\UnboundedSelfReferenceException::class)]
#[UsesClass(\SqlFixture\Plan\Choice\ChoiceValidation::class)]
#[UsesClass(\SqlFixture\Plan\Choice\PlanContents::class)]
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
        $schema = new TableSchema('order', [
            'id' => new ColumnDefinition('id', 'INT', autoIncrement: true),
            'status' => new ColumnDefinition('status', 'VARCHAR', length: 20),
        ], ['id']);
        $run = new GenerationRun([]);
        $run->reached('order', false);
        $run->record($schema, ['status' => 'paid']);

        self::assertSame(
            ['status' => 'paid'],
            $run->toSet(FixturePlan::table('order'))['order']
        );
    }

    #[Test]
    public function testAKeyNothingReadsIsLeftToTheDatabase(): void
    {
        $schema = new TableSchema('order', [
            'id' => new ColumnDefinition('id', 'INT', autoIncrement: true),
            'status' => new ColumnDefinition('status', 'VARCHAR', length: 20),
        ], ['id']);
        $run = new GenerationRun([]);

        self::assertArrayNotHasKey('id', $run->record($schema, ['status' => 'paid']));
    }

    #[Test]
    public function testAKeyARelationReadsIsStoodInFor(): void
    {
        $schema = new TableSchema('order', [
            'id' => new ColumnDefinition('id', 'INT', autoIncrement: true),
            'status' => new ColumnDefinition('status', 'VARCHAR', length: 20),
        ], ['id']);
        $run = new GenerationRun([]);

        self::assertSame(1, $run->record($schema, [], ['id'])['id']);
        self::assertSame(2, $run->record($schema, [], ['id'])['id']);
    }

    #[Test]
    public function testRecordKeepsAKeyTheCallerSupplied(): void
    {
        $schema = new TableSchema('order', [
            'id' => new ColumnDefinition('id', 'INT', autoIncrement: true),
            'status' => new ColumnDefinition('status', 'VARCHAR', length: 20),
        ], ['id']);
        $run = new GenerationRun([]);

        self::assertSame(100, $run->record($schema, ['id' => 100], ['id'])['id']);
    }

    #[Test]
    public function testOnlyAutoIncrementColumnsAreStoodInFor(): void
    {
        $schema = new TableSchema('order', [
            'id' => new ColumnDefinition('id', 'INT', autoIncrement: true),
            'status' => new ColumnDefinition('status', 'VARCHAR', length: 20),
        ], ['id']);
        $run = new GenerationRun([]);

        self::assertArrayNotHasKey('status', $run->record($schema, [], ['status']));
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
        $schema = new TableSchema('order', [
            'id' => new ColumnDefinition('id', 'INT', autoIncrement: true),
            'status' => new ColumnDefinition('status', 'VARCHAR', length: 20),
        ], ['id']);
        $run = new GenerationRun([]);
        $run->reached('order', true);
        $run->claim(['order']);
        $run->record($schema, ['status' => 'paid']);

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
        $schema = new TableSchema('order', [
            'id' => new ColumnDefinition('id', 'INT', autoIncrement: true),
            'status' => new ColumnDefinition('status', 'VARCHAR', length: 20),
        ], ['id']);
        $run = new GenerationRun([]);

        $row = $run->record($schema, [], ['status', 'id']);

        self::assertSame(1, $row['id']);
    }

    #[Test]
    public function testRecordReturnsTheWholeRow(): void
    {
        $schema = new TableSchema('order', [
            'id' => new ColumnDefinition('id', 'INT', autoIncrement: true),
            'status' => new ColumnDefinition('status', 'VARCHAR', length: 20),
        ], ['id']);
        $run = new GenerationRun([]);

        self::assertSame(
            ['status' => 'paid', 'id' => 1],
            $run->record($schema, ['status' => 'paid'], ['id'])
        );
    }
    public function testLastRowReturnsTheMostRecentRecordedValues(): void
    {
        $schema = new TableSchema('order', [
            'id' => new ColumnDefinition('id', 'INT', autoIncrement: true),
            'status' => new ColumnDefinition('status', 'VARCHAR', length: 20),
        ], ['id']);
        $run = new GenerationRun([]);
        $run->record($schema, ['status' => 'new']);
        $run->record($schema, ['status' => 'paid']);
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
