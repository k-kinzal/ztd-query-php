<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Construction;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Statement\Construction\Expression\ColumnUse;
use SqlSemantics\Statement\Construction\Query\RowDefinition;
use SqlSemantics\Statement\Construction\Query\RowsDefinition;
use SqlSemantics\Statement\Construction\RowsSnapshot;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Schema\Catalog;
use SqlSemantics\Statement\Schema\SearchPath;

#[CoversClass(RowsSnapshot::class)]
#[Small]
final class RowsSnapshotTest extends TestCase
{
    public function testEachRowPositionGetsItsOwnUseSitesInOneNewScope(): void
    {
        $catalog = new Catalog(new SearchPath(new Name('main')));
        $row = new RowDefinition(new ColumnUse(new Name('id')));
        $snapshot = new RowsSnapshot($catalog, new RowsDefinition($row, $row));
        $first = $snapshot->rows[0]->expressions[0]->references()[0];
        $second = $snapshot->rows[1]->expressions[0]->references()[0];
        self::assertNotSame($first, $second);
        self::assertSame($snapshot->scope, $first->scope);
        self::assertSame($snapshot->scope, $second->scope);
        self::assertSame([], $snapshot->scope->tables);
    }
}
