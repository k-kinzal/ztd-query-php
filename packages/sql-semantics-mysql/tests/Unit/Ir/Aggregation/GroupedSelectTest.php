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
use SqlSemantics\Platform\MySql\Ir\ColumnReference;
use SqlSemantics\Platform\MySql\Ir\Identifier;
use SqlSemantics\Platform\MySql\Ir\TableSource;

#[CoversClass(GroupedSelect::class)]
#[Small]
final class GroupedSelectTest extends TestCase
{
    public function testProfileIdIdentifiesTheGroupedDomain(): void
    {
        $source = new TableSource(new Identifier('app'), new Identifier('events'), []);
        $count = new GroupColumn(new Aggregate(AggregateOperator::Count), new Identifier('n'));
        $query = new GroupedSelect($source, [], [$count]);

        self::assertStringEndsWith('/native/grouped', $query->profileId());
    }

    public function testToStringKeepsTheQualifiedInputAndCount(): void
    {
        $source = new TableSource(new Identifier('app'), new Identifier('events'), []);
        $count = new GroupColumn(new Aggregate(AggregateOperator::Count), new Identifier('n'));
        $query = new GroupedSelect($source, [], [$count]);

        self::assertSame('SELECT count(*) AS `n` FROM `app`.`events` AS `__ztd_source`', $query->toString());
    }

    public function testRejectsAResultOutsideTheGroupingKeySet(): void
    {
        $source = new TableSource(new Identifier('app'), new Identifier('events'), [new Identifier('id'), new Identifier('delta')]);
        $column = new GroupColumn(new ColumnReference(1), new Identifier('value'));
        $this->expectExceptionMessage('A plain grouped result must read a grouping key.');

        new GroupedSelect($source, [new ColumnReference(0)], [$column]);
    }
}
