<?php

declare(strict_types=1);

namespace Tests\Unit\Plan;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFixture\Plan\ColumnRef;
use SqlFixture\Plan\PlanSyntaxException;

#[CoversClass(ColumnRef::class)]
#[UsesClass(PlanSyntaxException::class)]
#[UsesClass(\SqlFixture\Plan\FixturePlan::class)]
#[UsesClass(\SqlFixture\Plan\PlanParser::class)]
#[UsesClass(\SqlFixture\Plan\PlanPrinter::class)]
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
final class ColumnRefTest extends TestCase
{
    #[Test]
    public function testNamesATableAndItsColumns(): void
    {
        $ref = new ColumnRef('order', ['id']);

        self::assertSame('order', $ref->table);
        self::assertSame(['id'], $ref->columns);
    }

    #[Test]
    public function testOfBuildsFromVariadicColumns(): void
    {
        $ref = ColumnRef::of('order', 'shop_id', 'no');

        self::assertSame(['shop_id', 'no'], $ref->columns);
    }

    #[Test]
    public function testIsCompositeASingleColumnIsNotComposite(): void
    {
        self::assertFalse(ColumnRef::of('order', 'id')->isComposite());
    }

    #[Test]
    public function testSeveralColumnsAreComposite(): void
    {
        self::assertTrue(ColumnRef::of('order', 'shop_id', 'no')->isComposite());
    }

    #[Test]
    public function testToStringPrintsASingleColumnWithADot(): void
    {
        self::assertSame('order.id', ColumnRef::of('order', 'id')->toString());
    }

    #[Test]
    public function testPrintsCompositeColumnsInParentheses(): void
    {
        self::assertSame('order.(shop_id, no)', ColumnRef::of('order', 'shop_id', 'no')->toString());
    }

    #[Test]
    public function testCastsToItsWrittenForm(): void
    {
        self::assertSame('order.id', (string) ColumnRef::of('order', 'id'));
    }

    #[Test]
    public function testEqualsComparesTableAndColumns(): void
    {
        self::assertTrue(ColumnRef::of('order', 'id')->equals(ColumnRef::of('order', 'id')));
        self::assertFalse(ColumnRef::of('order', 'id')->equals(ColumnRef::of('order', 'no')));
        self::assertFalse(ColumnRef::of('order', 'id')->equals(ColumnRef::of('shipment', 'id')));
    }

    #[Test]
    public function testFromReadsASingleColumnEndpoint(): void
    {
        $ref = ColumnRef::from('order.id');

        self::assertSame('order', $ref->table);
        self::assertSame(['id'], $ref->columns);
    }

    #[Test]
    public function testFromReadsACompositeEndpoint(): void
    {
        $ref = ColumnRef::from('order.(shop_id, no)');

        self::assertSame(['shop_id', 'no'], $ref->columns);
    }

    #[Test]
    public function testFromStripsQuoting(): void
    {
        self::assertSame('order.id', ColumnRef::from('`order`."id"')->toString());
    }

    #[Test]
    public function testFromIgnoresSpaceAroundACompositeList(): void
    {
        self::assertSame(['shop_id', 'no'], ColumnRef::from('order. (shop_id, no) ')->columns);
    }

    #[Test]
    public function testFromDropsEmptyEntriesWithoutLeavingGapsInTheList(): void
    {
        self::assertSame(['a', 'b'], ColumnRef::from('order.(a, , b)')->columns);
    }

    #[Test]
    public function testOfKeepsColumnsInTheOrderGiven(): void
    {
        self::assertSame(['shop_id', 'no'], ColumnRef::of('order', 'shop_id', 'no')->columns);
    }

    #[Test]
    public function testOfReindexesColumnsSpreadFromAKeyedArray(): void
    {
        self::assertSame(['shop_id', 'no'], ColumnRef::of('order', ...['a' => 'shop_id', 'b' => 'no'])->columns);
    }
}
