<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\Alter;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Ir\ColumnReference;
use SqlSemantics\Platform\Sqlite\Ir\Identifier;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Alter\AddColumn;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Alter\AppendColumnBounds;
use SqlSemantics\Platform\Sqlite\Ir\Schema\TableColumn;

#[CoversClass(AppendColumnBounds::class)]
#[Small]
final class AppendColumnBoundsTest extends TestCase
{
    public function testCheckAdmitsAnExplicitRowidSpellingAbsentFromThePriorHeading(): void
    {
        $value = new AddColumn(
            new Identifier('main'),
            new Identifier('entries'),
            [new Identifier('id')],
            false,
            new TableColumn(new Identifier('rowid')),
        );

        self::assertSame('ALTER TABLE "main"."entries" ADD COLUMN "rowid"', $value->toString());
    }

    public function testBindingsKeepsOnlySelectedNamesInSlotOrder(): void
    {
        $first = new Identifier('first');
        $selected = new Identifier('selected');
        $bindings = (new AppendColumnBounds())->bindings(
            [$first, new Identifier('unrelated'), $selected],
            [new ColumnReference(2), new ColumnReference(0), new ColumnReference(2)],
        );

        self::assertSame([0 => $first, 2 => $selected], $bindings);
    }
}
