<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Aggregation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Ir\Aggregation\GroupColumn;
use SqlSemantics\Platform\PostgreSql\Ir\ColumnReference;
use SqlSemantics\Platform\PostgreSql\Ir\Identifier;

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
}
