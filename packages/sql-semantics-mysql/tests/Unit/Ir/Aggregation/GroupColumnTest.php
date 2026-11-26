<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Aggregation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Ir\Aggregation\Aggregate;
use SqlSemantics\Platform\MySql\Ir\Aggregation\AggregateOperator;
use SqlSemantics\Platform\MySql\Ir\Aggregation\GroupColumn;
use SqlSemantics\Platform\MySql\Ir\ColumnReference;
use SqlSemantics\Platform\MySql\Ir\Identifier;

#[CoversClass(GroupColumn::class)]
#[Small]
final class GroupColumnTest extends TestCase
{
    public function testRetainsTheNamedKey(): void
    {
        $slot = new ColumnReference(1);
        $column = new GroupColumn($slot, new Identifier('key'));

        self::assertSame($slot, $column->value);
        self::assertSame('key', $column->name->value);
    }

    public function testRejectsANameTheServerWouldTrim(): void
    {
        $this->expectExceptionMessage('A MySQL grouped result name cannot start with SQL whitespace.');

        new GroupColumn(new Aggregate(AggregateOperator::Count), new Identifier(' leading'));
    }
}
