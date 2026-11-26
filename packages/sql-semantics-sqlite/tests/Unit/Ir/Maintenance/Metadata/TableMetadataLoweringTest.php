<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Maintenance\Metadata;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Ir\Maintenance\Metadata\CatalogTable;
use SqlSemantics\Platform\Sqlite\Ir\Maintenance\Metadata\TableInspection;
use SqlSemantics\Platform\Sqlite\Ir\Maintenance\Metadata\TableListing;
use SqlSemantics\Platform\Sqlite\Ir\Maintenance\Metadata\TableMetadataLowering;
use SqlSemantics\Platform\Sqlite\Ir\Maintenance\RelationTarget;
use SqlSemantics\Platform\Sqlite\Semantics;

#[CoversClass(TableMetadataLowering::class)]
#[Small]
final class TableMetadataLoweringTest extends TestCase
{
    public function testLowerRetainsTheNativeListingFilter(): void
    {
        $listing = (new Semantics())->analyze("PRAGMA table_list('sqlite_master')");

        self::assertInstanceOf(TableListing::class, $listing);
        self::assertSame('sqlite_master', $listing->nameFilter?->value);
        self::assertNull($listing->schema);
    }

    public function testTargetChoosesTheTemporaryRelationAheadOfMain(): void
    {
        $semantics = new Semantics();
        $context = $semantics->context(['CREATE TABLE main.items(value TEXT)', 'CREATE TEMP TABLE items(temp_value INT)']);
        $inspection = $semantics->analyze('PRAGMA table_info(items)', $context);

        self::assertInstanceOf(TableInspection::class, $inspection);
        self::assertInstanceOf(RelationTarget::class, $inspection->target);
        self::assertSame('temp', $inspection->target->schema->value);
    }

    public function testTargetResolvesTheTemporaryCatalogWithoutAnExplicitScope(): void
    {
        $inspection = (new Semantics())->analyze('PRAGMA table_xinfo(sqlite_temp_schema)');

        self::assertInstanceOf(TableInspection::class, $inspection);
        self::assertInstanceOf(CatalogTable::class, $inspection->target);
        self::assertSame('temp', $inspection->target->schema->value);
        self::assertNull($inspection->schema);
    }
}
