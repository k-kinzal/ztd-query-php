<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Aggregation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Ir\Aggregation\Aggregate;
use SqlSemantics\Platform\PostgreSql\Ir\Aggregation\AggregateOperator;
use SqlSemantics\Platform\PostgreSql\Ir\Aggregation\GroupColumn;
use SqlSemantics\Platform\PostgreSql\Ir\Aggregation\GroupedSelect;
use SqlSemantics\Platform\PostgreSql\Ir\Aggregation\GroupedSql;
use SqlSemantics\Platform\PostgreSql\Ir\ColumnReference;
use SqlSemantics\Platform\PostgreSql\Ir\Identifier;
use SqlSemantics\Platform\PostgreSql\Ir\SelectSql;
use SqlSemantics\Platform\PostgreSql\Ir\TableSource;

#[CoversClass(GroupedSql::class)]
#[Small]
final class GroupedSqlTest extends TestCase
{
    public function testOperandRendersTheNativeAggregate(): void
    {
        $source = new TableSource(new Identifier('public'), new Identifier('events'), [new Identifier('id')]);
        $count = new Aggregate(AggregateOperator::Count, new ColumnReference(0), true);

        self::assertSame('pg_catalog.count(DISTINCT "__ztd_source"."id")', (new GroupedSql())->operand($count, $source, new SelectSql()));
    }

    public function testRenderReadsTheExplicitGroupAndAggregateSlots(): void
    {
        $id = new ColumnReference(0);
        $amount = new ColumnReference(1);
        $source = new TableSource(new Identifier('app'), new Identifier('events'), [new Identifier('id'), new Identifier('amount')]);
        $sum = new GroupColumn(new Aggregate(AggregateOperator::Sum, $amount, true), new Identifier('total'));
        $query = new GroupedSelect($source, [$id], [$sum]);

        self::assertSame(
            'SELECT pg_catalog.sum(DISTINCT "__ztd_source"."amount") AS "total" FROM "app"."events" AS "__ztd_source" GROUP BY "__ztd_source"."id"',
            (new GroupedSql())->render($query),
        );
    }
}
