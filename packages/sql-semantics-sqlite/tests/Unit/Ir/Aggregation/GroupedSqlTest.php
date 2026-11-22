<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Aggregation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Ir\Aggregation\Count;
use SqlSemantics\Platform\Sqlite\Ir\Aggregation\GroupColumn;
use SqlSemantics\Platform\Sqlite\Ir\Aggregation\GroupedSelect;
use SqlSemantics\Platform\Sqlite\Ir\Aggregation\GroupedSql;
use SqlSemantics\Platform\Sqlite\Ir\Aggregation\GroupKey;
use SqlSemantics\Platform\Sqlite\Ir\Collation;
use SqlSemantics\Platform\Sqlite\Ir\ColumnReference;
use SqlSemantics\Platform\Sqlite\Ir\Identifier;
use SqlSemantics\Platform\Sqlite\Ir\SelectSql;
use SqlSemantics\Platform\Sqlite\Ir\TableSource;

#[CoversClass(GroupedSql::class)]
#[Small]
final class GroupedSqlTest extends TestCase
{
    public function testRenderEmitsBothEffectiveCollations(): void
    {
        $reference = new ColumnReference(0);
        $source = new TableSource(new Identifier('main'), new Identifier('items'), [new Identifier('key')]);
        $key = new GroupKey($reference, Collation::RightTrim);
        $count = new GroupColumn(new Count($reference, Collation::NoCase), new Identifier('n'));
        $sql = (new GroupedSql())->render(new GroupedSelect($source, [$key], [$count]));

        self::assertSame('SELECT count(DISTINCT "__ztd_source"."key" COLLATE NOCASE) AS "n" FROM "main"."items" AS "__ztd_source" GROUP BY "__ztd_source"."key" COLLATE RTRIM', $sql);
    }

    public function testCountEmitsTheCountAllArgument(): void
    {
        $count = new Count();
        $query = new GroupedSelect(null, [], [new GroupColumn($count, new Identifier('n'))]);

        self::assertSame('count(*)', (new GroupedSql())->count($count, $query, new SelectSql()));
    }
}
