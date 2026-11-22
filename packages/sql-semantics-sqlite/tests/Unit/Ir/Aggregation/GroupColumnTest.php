<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Aggregation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Ir\Aggregation\Count;
use SqlSemantics\Platform\Sqlite\Ir\Aggregation\GroupColumn;
use SqlSemantics\Platform\Sqlite\Ir\Identifier;

#[CoversClass(GroupColumn::class)]
#[Small]
final class GroupColumnTest extends TestCase
{
    public function testRetainsOrderedResultNameAndCount(): void
    {
        $count = new Count();
        $column = new GroupColumn($count, new Identifier('n'));

        self::assertSame($count, $column->value);
        self::assertSame('n', $column->name->value);
    }
}
