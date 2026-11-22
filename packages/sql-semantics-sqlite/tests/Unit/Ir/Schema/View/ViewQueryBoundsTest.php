<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\View;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Ir\Aggregation\Count;
use SqlSemantics\Platform\Sqlite\Ir\Aggregation\GroupColumn;
use SqlSemantics\Platform\Sqlite\Ir\Aggregation\GroupedSelect;
use SqlSemantics\Platform\Sqlite\Ir\Identifier;
use SqlSemantics\Platform\Sqlite\Ir\IntegerLiteral;
use SqlSemantics\Platform\Sqlite\Ir\ResultColumn;
use SqlSemantics\Platform\Sqlite\Ir\Schema\View\ViewQueryBounds;
use SqlSemantics\Platform\Sqlite\Ir\Select;
use SqlSemantics\Platform\Sqlite\Ir\TableSource;

#[CoversClass(ViewQueryBounds::class)]
#[Small]
final class ViewQueryBoundsTest extends TestCase
{
    public function testCheckAcceptsAResolvedOneColumnProjection(): void
    {
        $select = new Select([new ResultColumn(new IntegerLiteral('7'), new Identifier('answer'))]);

        (new ViewQueryBounds())->check(new Identifier('main'), $select, 1);

        self::assertSame('answer', $select->columns[0]->name->value);
    }

    public function testGroupedAcceptsTheCountOfAnImplicitRow(): void
    {
        $name = new Identifier('main');
        $query = new GroupedSelect(null, [], [new GroupColumn(new Count(), new Identifier('entries'))]);

        (new ViewQueryBounds())->grouped($name, $query, 1);

        self::assertSame('entries', $query->columns[0]->name->value);
    }

    public function testSourceAcceptsAnExplicitMainTableInATempView(): void
    {
        $source = new TableSource(new Identifier('main'), new Identifier('base'), []);

        (new ViewQueryBounds())->source(new Identifier('temp'), $source);

        self::assertSame('main', $source->schema->value);
    }
}
