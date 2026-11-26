<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Aggregation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Ir\Aggregation\Aggregate;
use SqlSemantics\Platform\MySql\Ir\Aggregation\AggregateOperator;
use SqlSemantics\Platform\MySql\Ir\Aggregation\GroupColumn;
use SqlSemantics\Platform\MySql\Ir\Aggregation\GroupedSelect;
use SqlSemantics\Platform\MySql\Ir\Aggregation\GroupedSql;
use SqlSemantics\Platform\MySql\Ir\ColumnReference;
use SqlSemantics\Platform\MySql\Ir\Identifier;
use SqlSemantics\Platform\MySql\Ir\SelectSql;
use SqlSemantics\Platform\MySql\Ir\TableSource;

#[CoversClass(GroupedSql::class)]
#[Small]
final class GroupedSqlTest extends TestCase
{
    public function testRenderReadsTheExplicitGroupAndAggregateSlots(): void
    {
        $id = new ColumnReference(0);
        $amount = new ColumnReference(1);
        $source = new TableSource(new Identifier('app'), new Identifier('events'), [new Identifier('id'), new Identifier('amount')]);
        $sum = new GroupColumn(new Aggregate(AggregateOperator::Sum, $amount, true), new Identifier('total'));
        $query = new GroupedSelect($source, [$id], [$sum]);

        self::assertSame(
            'SELECT sum(DISTINCT `__ztd_source`.`amount`) AS `total` FROM `app`.`events` AS `__ztd_source` GROUP BY `__ztd_source`.`id`',
            (new GroupedSql())->render($query),
        );
    }
    public function testOperandRendersTheNativeAggregate(): void
    {
        $source = new TableSource(new Identifier('app'), new Identifier('events'), []);
        $count = new Aggregate(AggregateOperator::Count);

        self::assertSame('count(*)', (new GroupedSql())->operand($count, $source, new SelectSql()));
    }
}
