<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Maintenance\Metadata;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Ir\Identifier;
use SqlSemantics\Platform\Sqlite\Ir\Maintenance\Metadata\CatalogTable;
use SqlSemantics\Platform\Sqlite\Ir\Maintenance\Metadata\TableInspection;
use SqlSemantics\Platform\Sqlite\Ir\Maintenance\Metadata\TableInspectionKind;
use SqlSemantics\Platform\Sqlite\Ir\Maintenance\RelationTarget;

#[CoversClass(TableInspection::class)]
#[Small]
final class TableInspectionTest extends TestCase
{
    public function testToStringWritesAResolvedCatalogOrTable(): void
    {
        $schema = new Identifier('archive "data"');
        $target = new RelationTarget($schema, new Identifier('daily "values"'));

        self::assertSame('PRAGMA "archive ""data""".table_xinfo("daily ""values""")', (new TableInspection(TableInspectionKind::ExtendedColumns, $target, $schema))->toString());
        self::assertSame('PRAGMA index_list("sqlite_temp_master")', (new TableInspection(TableInspectionKind::Indexes, new CatalogTable(new Identifier('temp'))))->toString());
        self::assertSame('PRAGMA foreign_key_list', (new TableInspection(TableInspectionKind::ForeignKeys))->toString());
    }

    public function testProfileIdUsesThePinnedNativeProfile(): void
    {
        self::assertSame('sqlite-3.47.2/utf8/short-column-names/numbered-parameters', (new TableInspection(TableInspectionKind::Columns))->profileId());
    }
}
