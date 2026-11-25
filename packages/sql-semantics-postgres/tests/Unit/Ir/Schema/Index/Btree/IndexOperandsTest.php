<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\Index\Btree;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Ir\From\ColumnKind;
use SqlSemantics\Platform\PostgreSql\Ir\Identifier;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Index\Btree\Binding\IndexTarget;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Index\Btree\IndexOperands;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Index\Btree\IndexOrder;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Declaration\IndexHeading;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\PostgreSql\Statement\Query\NullsOrder;
use SqlSemantics\Platform\PostgreSql\Statement\Query\SortDirection;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Index\ColumnKey;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Index\IndexElement;

#[CoversClass(IndexOperands::class)]
#[Small]
final class IndexOperandsTest extends TestCase
{
    public function testKeyKeepsExplicitNullPlacementForDescendingOrder(): void
    {
        $table = new IndexHeading(new IndexTarget(new Identifier('public'), new Identifier('orders')), [new Identifier('id')], [ColumnKind::Int4]);
        $element = new IndexElement(new ColumnKey(new Name('id')), direction: SortDirection::Descending, nulls: NullsOrder::Last);

        self::assertSame(IndexOrder::DescendingNullsLast, (new IndexOperands())->key($table, $element, false)->order);
    }

    public function testCoveringRetainsThePhysicalColumnSlot(): void
    {
        $table = new IndexHeading(new IndexTarget(new Identifier('public'), new Identifier('orders')), [new Identifier('id'), new Identifier('visible')], [ColumnKind::Int4, ColumnKind::Int4]);
        $element = new IndexElement(new ColumnKey(new Name('visible')));

        self::assertSame(1, (new IndexOperands())->covering($table, $element)->slot);
    }

    public function testSlotUsesTheExactDecodedIdentifier(): void
    {
        $table = new IndexHeading(new IndexTarget(new Identifier('public'), new Identifier('orders')), [new Identifier('id'), new Identifier('Id')], [ColumnKind::Int4, ColumnKind::Int4]);
        $element = new IndexElement(new ColumnKey(new Name('Id')));

        self::assertSame(1, (new IndexOperands())->slot($table, $element));
    }
}
