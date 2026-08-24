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
final class ColumnRefTest extends TestCase
{
    #[Test]
    public function namesATableAndItsColumns(): void
    {
        $ref = new ColumnRef('order', ['id']);

        self::assertSame('order', $ref->table);
        self::assertSame(['id'], $ref->columns);
    }

    #[Test]
    public function ofBuildsFromVariadicColumns(): void
    {
        $ref = ColumnRef::of('order', 'shop_id', 'no');

        self::assertSame(['shop_id', 'no'], $ref->columns);
    }

    #[Test]
    public function aSingleColumnIsNotComposite(): void
    {
        self::assertFalse(ColumnRef::of('order', 'id')->isComposite());
    }

    #[Test]
    public function severalColumnsAreComposite(): void
    {
        self::assertTrue(ColumnRef::of('order', 'shop_id', 'no')->isComposite());
    }

    #[Test]
    public function printsASingleColumnWithADot(): void
    {
        self::assertSame('order.id', ColumnRef::of('order', 'id')->toString());
    }

    #[Test]
    public function printsCompositeColumnsInParentheses(): void
    {
        self::assertSame('order.(shop_id, no)', ColumnRef::of('order', 'shop_id', 'no')->toString());
    }

    #[Test]
    public function castsToItsWrittenForm(): void
    {
        self::assertSame('order.id', (string) ColumnRef::of('order', 'id'));
    }

    #[Test]
    public function equalsComparesTableAndColumns(): void
    {
        self::assertTrue(ColumnRef::of('order', 'id')->equals(ColumnRef::of('order', 'id')));
        self::assertFalse(ColumnRef::of('order', 'id')->equals(ColumnRef::of('order', 'no')));
        self::assertFalse(ColumnRef::of('order', 'id')->equals(ColumnRef::of('shipment', 'id')));
    }

    #[Test]
    public function anEmptyTableNameIsRejected(): void
    {
        $this->expectException(PlanSyntaxException::class);
        $this->expectExceptionMessage('must name a table');

        new ColumnRef('', ['id']);
    }

    #[Test]
    public function anEndpointWithoutColumnsIsRejected(): void
    {
        $this->expectException(PlanSyntaxException::class);
        $this->expectExceptionMessage('names no columns');

        new ColumnRef('order', []);
    }

    #[Test]
    public function fromReadsASingleColumnEndpoint(): void
    {
        $ref = ColumnRef::from('order.id');

        self::assertSame('order', $ref->table);
        self::assertSame(['id'], $ref->columns);
    }

    #[Test]
    public function fromReadsACompositeEndpoint(): void
    {
        $ref = ColumnRef::from('order.(shop_id, no)');

        self::assertSame(['shop_id', 'no'], $ref->columns);
    }

    #[Test]
    public function fromStripsQuoting(): void
    {
        self::assertSame('order.id', ColumnRef::from('`order`."id"')->toString());
    }

    #[Test]
    public function fromRejectsAnEndpointWithoutAColumn(): void
    {
        $this->expectException(PlanSyntaxException::class);
        $this->expectExceptionMessage("'.' after the table name");

        ColumnRef::from('order');
    }

    #[Test]
    public function fromIgnoresSpaceAroundACompositeList(): void
    {
        self::assertSame(['shop_id', 'no'], ColumnRef::from('order. (shop_id, no) ')->columns);
    }

    #[Test]
    public function fromDropsEmptyEntriesWithoutLeavingGapsInTheList(): void
    {
        self::assertSame(['a', 'b'], ColumnRef::from('order.(a, , b)')->columns);
    }

    #[Test]
    public function fromRejectsACompositeListThatIsNeverClosed(): void
    {
        $this->expectException(PlanSyntaxException::class);
        $this->expectExceptionMessage("expected ')'");

        ColumnRef::from('order.(a, b');
    }

    #[Test]
    public function ofKeepsColumnsInTheOrderGiven(): void
    {
        self::assertSame(['shop_id', 'no'], ColumnRef::of('order', 'shop_id', 'no')->columns);
    }

    #[Test]
    public function ofReindexesColumnsSpreadFromAKeyedArray(): void
    {
        self::assertSame(['shop_id', 'no'], ColumnRef::of('order', ...['a' => 'shop_id', 'b' => 'no'])->columns);
    }
}
