<?php

declare(strict_types=1);

namespace Tests\Unit\Registry;

use MySqlMemory\Registry\SpatialCatalog;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(SpatialCatalog::class)]
#[Small]
final class SpatialCatalogTest extends TestCase
{
    public function testInstalledHoldsTheSystemsOfANewServer(): void
    {
        $installed = SpatialCatalog::installed();

        self::assertSame([5238, 'WGS 84', '', 'WGS 84 / Pseudo-Mercator'], [count($installed), $installed[4326], $installed[0], $installed[3857]]);
    }

    public function testDataPreservesReleaseSpecificCatalogs(): void
    {
        self::assertCount(5152, SpatialCatalog::data('8.0.44'));
        self::assertCount(5238, SpatialCatalog::data('8.4.7'));
        self::assertSame(['', null, null, '', null], SpatialCatalog::data()[0]);
    }

    public function testKeysFindsAnInstalledSystemByName(): void
    {
        self::assertSame(4326, SpatialCatalog::keys()['WGS 84'] ?? null);
    }

    public function testNameAnswersNullForAnSridWithoutSystem(): void
    {
        self::assertSame(['WGS 84', null], [(new SpatialCatalog())->name(4326), (new SpatialCatalog())->name(15)]);
    }

    public function testNamedIgnoresLetterCaseAndTheSystemItself(): void
    {
        $catalog = new SpatialCatalog();

        self::assertSame([4326, null], [$catalog->named('wgs 84', 1000000001), $catalog->named('wgs 84', 4326)]);
    }

    public function testDefineAddsASystem(): void
    {
        $catalog = new SpatialCatalog();
        $catalog->define(1000000001, 'mine');

        self::assertSame(['mine', 1000000001], [$catalog->name(1000000001), $catalog->named('MINE', 0)]);
    }

    public function testOrganizationTracksReplacementAndRemoval(): void
    {
        $catalog = new SpatialCatalog();
        self::assertSame(4326, $catalog->organization('epsg', 4326, 500001));
        $catalog->define(4326, 'replaced', 'custom', 7);
        self::assertNull($catalog->organization('EPSG', 4326, 500001));
        self::assertSame(4326, $catalog->organization('CUSTOM', 7, 500001));
        $catalog->drop(4326);
        self::assertNull($catalog->organization('custom', 7, 500001));
    }

    public function testAttributesRetainsEveryCreatedAttribute(): void
    {
        $catalog = new SpatialCatalog();
        $catalog->define(500001, 'custom', 'LOCAL', 7, 'definition', 'description');
        self::assertSame(['LOCAL', 7, 'definition', 'description'], $catalog->attributes(500001));
        self::assertSame(['EPSG', 4326], array_slice($catalog->attributes(4326), 0, 2));
        self::assertStringStartsWith('GEOGCS[', $catalog->attributes(4326)[2]);
    }

    public function testDropRemovesAnInstalledSystem(): void
    {
        $catalog = new SpatialCatalog();
        $catalog->drop(4326);

        self::assertSame([null, null], [$catalog->name(4326), $catalog->named('WGS 84', 0)]);
    }
}
