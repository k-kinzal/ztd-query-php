<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Maintenance\Metadata;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Ir\Identifier;
use SqlSemantics\Platform\Sqlite\Ir\Maintenance\Metadata\CatalogAliases;
use SqlSemantics\Platform\Sqlite\Ir\Maintenance\Metadata\TableInspectionKind;

#[CoversClass(CatalogAliases::class)]
#[Small]
final class CatalogAliasesTest extends TestCase
{
    public function testResolveSelectsTheNativeSchemaForCatalogAliases(): void
    {
        $aliases = new CatalogAliases();

        self::assertSame('temp', $aliases->resolve(new Identifier('sqlite_temp_schema'), null)?->schema->value);
        self::assertSame('archive', $aliases->resolve(new Identifier('sqlite_schema'), new Identifier('archive'))?->schema->value);
        self::assertNull($aliases->resolve(new Identifier('sqlite_temp_master'), new Identifier('main')));
    }

    public function testIsCatalogNameIsCaseInsensitive(): void
    {
        self::assertTrue((new CatalogAliases())->isCatalogName(new Identifier('SQLITE_TEMP_SCHEMA')));
        self::assertFalse((new CatalogAliases())->isCatalogName(new Identifier('sqlite_stat1')));
    }

    public function testPermitsAbsenceDistinguishesColumnLookupsFromIndexLookups(): void
    {
        $aliases = new CatalogAliases();
        $eponym = new Identifier('json_each');

        self::assertFalse($aliases->permitsAbsence(TableInspectionKind::Columns, $eponym, null));
        self::assertTrue($aliases->permitsAbsence(TableInspectionKind::Indexes, $eponym, null));
        self::assertFalse($aliases->permitsAbsence(TableInspectionKind::ForeignKeys, new Identifier('sqlite_stat1'), null));
    }
}
