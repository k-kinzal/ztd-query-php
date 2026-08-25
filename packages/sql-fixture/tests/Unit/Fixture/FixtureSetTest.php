<?php

declare(strict_types=1);

namespace Tests\Unit\Fixture;

use LogicException;
use OutOfBoundsException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use SqlFixture\Fixture\FixtureSet;

#[CoversClass(FixtureSet::class)]
final class FixtureSetTest extends TestCase
{
    #[Test]
    public function readsByPositionInThePlansOrder(): void
    {
        $set = new FixtureSet(
            ['order' => [['id' => 1]], 'order_detail' => [['id' => 1], ['id' => 2]]],
            ['order' => false, 'order_detail' => true],
            ['order', 'order_detail']
        );

        self::assertSame(['id' => 1], $set->row(0));
        self::assertCount(2, $set->rows(1));
    }

    #[Test]
    public function destructuresIntoTheTablesThePlanNames(): void
    {
        $set = new FixtureSet(
            ['order' => [['id' => 1]], 'order_detail' => [['id' => 9]]],
            ['order' => false, 'order_detail' => true],
            ['order', 'order_detail']
        );

        [$order, $details] = $set;

        self::assertSame(['id' => 1], $order);
        self::assertSame([['id' => 9]], $details);
    }

    #[Test]
    public function readsByTableName(): void
    {
        $set = new FixtureSet(['order' => [['id' => 1]]], ['order' => false], ['order']);

        self::assertSame(['id' => 1], $set['order']);
    }

    #[Test]
    public function aTableHoldingAListReadsBackAsAList(): void
    {
        $set = new FixtureSet(['order_detail' => [['id' => 1]]], ['order_detail' => true], ['order_detail']);

        self::assertSame([['id' => 1]], $set['order_detail']);
    }

    #[Test]
    public function testRowIsNullWhereNothingWasGenerated(): void
    {
        $set = new FixtureSet(['order_shipping' => []], ['order_shipping' => false], ['order_shipping']);

        self::assertNull($set->row('order_shipping'));
        self::assertNull($set['order_shipping']);
    }

    #[Test]
    public function rowRefusesATableHoldingAList(): void
    {
        $set = new FixtureSet(['order_detail' => [['id' => 1]]], ['order_detail' => true], ['order_detail']);

        $this->expectException(OutOfBoundsException::class);
        $this->expectExceptionMessage('holds a list of rows');

        $set->row('order_detail');
    }

    #[Test]
    public function testRowsAlwaysReturnsAList(): void
    {
        $set = new FixtureSet(
            ['order' => [['id' => 1]], 'order_detail' => [['id' => 2]], 'shipping' => []],
            ['order' => false, 'order_detail' => true, 'shipping' => false],
            ['order', 'order_detail', 'shipping']
        );

        self::assertSame([['id' => 1]], $set->rows('order'));
        self::assertSame([['id' => 2]], $set->rows('order_detail'));
        self::assertSame([], $set->rows('shipping'));
    }

    #[Test]
    public function countsTheTablesNotTheRows(): void
    {
        $set = new FixtureSet(
            ['order' => [['id' => 1]], 'order_detail' => [['id' => 1], ['id' => 2]]],
            ['order' => false, 'order_detail' => true],
            ['order', 'order_detail']
        );

        self::assertCount(2, $set);
    }

    #[Test]
    public function iteratesInThePlansOrder(): void
    {
        $set = new FixtureSet(
            ['order' => [['id' => 1]], 'order_detail' => [['id' => 2]]],
            ['order' => false, 'order_detail' => true],
            ['order', 'order_detail']
        );

        self::assertSame([['id' => 1], [['id' => 2]]], iterator_to_array($set));
    }

    #[Test]
    public function testTablesReportsWhichTablesItHolds(): void
    {
        $set = new FixtureSet(['order' => [['id' => 1]]], ['order' => false], ['order']);

        self::assertTrue(isset($set['order']));
        self::assertFalse(isset($set['nope']));
        self::assertSame(['order'], $set->tables());
        self::assertSame(['order' => ['id' => 1]], $set->toArray());
    }

    #[Test]
    public function anUnknownTableReadsBackAsNothing(): void
    {
        $set = new FixtureSet(['order' => [['id' => 1]]], ['order' => false], ['order']);

        self::assertNull($set['nope']);
        self::assertSame([], $set->rows('nope'));
    }

    #[Test]
    public function testGetReadsTheEntryWhicheverShapeItHas(): void
    {
        $set = new FixtureSet(
            ['order' => [['id' => 1]], 'order_detail' => [['id' => 2]]],
            ['order' => false, 'order_detail' => true],
            ['order', 'order_detail']
        );

        self::assertSame(['id' => 1], $set->get('order'));
        self::assertSame([['id' => 2]], $set->get('order_detail'));
    }

    #[Test]
    public function anUnknownTableIsNotTreatedAsAList(): void
    {
        $set = new FixtureSet(['order' => [['id' => 1]]], ['order' => false], ['order']);

        self::assertNull($set->row('nope'));
    }

    #[Test]
    public function testToArrayKeepsEveryTable(): void
    {
        $set = new FixtureSet(
            ['order' => [['id' => 1]], 'order_detail' => [['id' => 2]]],
            ['order' => false, 'order_detail' => true],
            ['order', 'order_detail']
        );

        self::assertSame(
            ['order' => ['id' => 1], 'order_detail' => [['id' => 2]]],
            $set->toArray()
        );
    }

    #[Test]
    public function aPositionPastTheEndReadsAsNothing(): void
    {
        $set = new FixtureSet(['order' => [['id' => 1]]], ['order' => false], ['order']);

        self::assertNull($set[7]);
        self::assertFalse(isset($set[7]));
    }

    public function testOffsetExistsReportsWhetherThePlanGeneratedRowsForATable(): void
    {
        $set = new FixtureSet(['order' => [['id' => 1]]], ['order' => false], ['order']);

        self::assertTrue(isset($set['order']));
        self::assertFalse(isset($set['nothing']));
    }

    public function testOffsetGetReadsATableTheWayGetDoes(): void
    {
        $set = new FixtureSet(['order' => [['id' => 1]]], ['order' => false], ['order']);

        self::assertSame(['id' => 1], $set['order']);
    }

    public function testOffsetSetRefusesToChangeWhatWasGenerated(): void
    {
        $set = new FixtureSet(['order' => [['id' => 1]]], ['order' => false], ['order']);

        $this->expectException(LogicException::class);

        $set['order'] = [];
    }

    public function testOffsetUnsetRefusesToRemoveWhatWasGenerated(): void
    {
        $set = new FixtureSet(['order' => [['id' => 1]]], ['order' => false], ['order']);

        $this->expectException(LogicException::class);

        unset($set['order']);
    }

    public function testGetIteratorWalksTheTablesInTheOrderThePlanNamesThem(): void
    {
        $set = new FixtureSet(
            ['order' => [['id' => 1]], 'detail' => [['id' => 2], ['id' => 3]]],
            ['order' => false, 'detail' => true],
            ['order', 'detail'],
        );

        self::assertSame([['id' => 1], [['id' => 2], ['id' => 3]]], iterator_to_array($set));
    }

    public function testCountAnswersHowManyTablesTheGenerationProducedRowsFor(): void
    {
        $set = new FixtureSet(['a' => [[]], 'b' => [[]]], ['a' => false, 'b' => false], ['a', 'b']);

        self::assertCount(2, $set);
    }

    public function testFirstRowAnswersTheFirstRowGeneratedForATable(): void
    {
        $set = new FixtureSet(['order' => [['id' => 1], ['id' => 2]]], ['order' => true], ['order']);

        self::assertSame(['id' => 1], $set->firstRow('order'));
    }

    public function testFirstRowAnswersNothingForATableWithNoRows(): void
    {
        $set = new FixtureSet([], ['order' => true], ['order']);

        self::assertNull($set->firstRow('order'));
    }

    public function testResolveReadsAPositionAsTheTableThePlanNamesThere(): void
    {
        $set = new FixtureSet(['a' => [[]], 'b' => [[]]], ['a' => false, 'b' => false], ['a', 'b']);

        self::assertSame('b', $set->resolve(1));
        self::assertSame('a', $set->resolve('a'));
    }

    public function testResolveAnswersNothingForAPositionThePlanDoesNotReach(): void
    {
        $set = new FixtureSet(['a' => [[]]], ['a' => false], ['a']);

        self::assertSame('', $set->resolve(9));
    }
}
