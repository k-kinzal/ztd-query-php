<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Aggregation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Ir\Aggregation\Count;
use SqlSemantics\Platform\Sqlite\Ir\Aggregation\GroupColumn;
use SqlSemantics\Platform\Sqlite\Ir\Aggregation\GroupedSelect;
use SqlSemantics\Platform\Sqlite\Ir\Aggregation\GroupKey;
use SqlSemantics\Platform\Sqlite\Ir\ColumnReference;
use SqlSemantics\Platform\Sqlite\Ir\Identifier;

#[CoversClass(GroupedSelect::class)]
#[Small]
final class GroupedSelectTest extends TestCase
{
    public function testProfileIdIdentifiesTheBuiltinGroupingRules(): void
    {
        $select = new GroupedSelect(null, [], [new GroupColumn(new Count(), new Identifier('n'))]);

        self::assertStringEndsWith('/grouped-builtin', $select->profileId());
    }

    public function testToStringEmitsCountOfTheImplicitRow(): void
    {
        $select = new GroupedSelect(null, [], [new GroupColumn(new Count(), new Identifier('n'))]);

        self::assertSame('SELECT count(*) AS "n"', $select->toString());
    }

    public function testRejectsAKeyWithoutAnInput(): void
    {
        $this->expectExceptionMessage('Each grouping key must belong to its input.');

        new GroupedSelect(null, [new GroupKey(new ColumnReference(0))], [new GroupColumn(new Count(), new Identifier('n'))]);
    }
}
