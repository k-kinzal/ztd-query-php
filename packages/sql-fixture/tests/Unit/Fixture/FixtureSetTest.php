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
    public function testReadsByPositionInThePlansOrder(): void
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
    public function testOffsetGetSupportsListAssignment(): void
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
    public function testReadsByTableName(): void
    {
        $set = new FixtureSet(['order' => [['id' => 1]]], ['order' => false], ['order']);

        self::assertSame(['id' => 1], $set['order']);
    }

    #[Test]
    public function testATableHoldingAListReadsBackAsAList(): void
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
    public function testRowRefusesATableHoldingAList(): void
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
    public function testCountsTheTablesNotTheRows(): void
    {
        $set = new FixtureSet(
            ['order' => [['id' => 1]], 'order_detail' => [['id' => 1], ['id' => 2]]],
            ['order' => false, 'order_detail' => true],
            ['order', 'order_detail']
        );

        self::assertCount(2, $set);
    }

    #[Test]
    public function testIteratesInThePlansOrder(): void
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
    public function testAnUnknownTableReadsBackAsNothing(): void
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
    public function testAnUnknownTableIsNotTreatedAsAList(): void
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
    public function testAPositionPastTheEndReadsAsNothing(): void
    {
        $set = new FixtureSet(['order' => [['id' => 1]]], ['order' => false], ['order']);

        self::assertNull($set[7]);
        self::assertFalse(isset($set[7]));
    }
    public function testOffsetExistsTracksNamesAndPositions(): void
    {
        $set = new FixtureSet(['a' => []], ['a' => true], ['a']);
        self::assertTrue(isset($set['a']));
        self::assertTrue(isset($set[0]));
        self::assertFalse(isset($set[1]));
    }
    public function testGetIteratorPreservesPlanOrder(): void
    {
        $set = new FixtureSet(['a' => [['id' => 2]], 'b' => []], ['a' => false, 'b' => true], ['b', 'a']);
        self::assertSame([[], ['id' => 2]], iterator_to_array($set));
    }
    public function testOffsetSetRejectsMutation(): void
    {
        $set = new FixtureSet(['order' => [['id' => 1]]], ['order' => false], ['order']);
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('A FixtureSet is read-only.');
        $set['order'] = ['id' => 2];
    }

    public function testOffsetUnsetRejectsRemoval(): void
    {
        $set = new FixtureSet(['order' => [['id' => 1]]], ['order' => false], ['order']);
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('A FixtureSet is read-only.');
        unset($set['order']);
    }
}
