<?php

declare(strict_types=1);

namespace Tests\Unit\Value;

use MySqlMemory\Value\Calendar;
use MySqlMemory\Value\Zone;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(Zone::class)]
#[Small]
final class ZoneTest extends TestCase
{
    public function testNamedReadsOffsetsSystemAndTheNamesOfTheTables(): void
    {
        self::assertSame(['+05:30', '-00:30', 'SYSTEM', 'Europe/Paris', 'UTC'], [Zone::named('+5:30')?->name, Zone::named('-0:30')?->name, Zone::named('system')?->name, Zone::named('europe/paris')?->name, Zone::named('utc ')?->name]);
    }

    public function testNamedUsesOnlyTheInstalledNamesIncludingPrefixedAliases(): void
    {
        $installed = ['posix/europe/paris' => 'posix/Europe/Paris', 'europe/kyiv' => 'Europe/Kyiv'];
        self::assertSame(['posix/Europe/Paris', 'Europe/Kyiv', null, null], [Zone::named('POSIX/europe/paris', $installed)?->name, Zone::named('Europe/Kyiv', $installed)?->name, Zone::named('Asia/Tokyo', $installed)?->name, Zone::named('Europe/Kyiv', [])?->name]);
    }

    public function testReadRefusesWhatNamesNoZone(): void
    {
        self::assertSame([null, null, null, null], [Zone::read('+14:01'), Zone::read('-14:00'), Zone::read(' UTC'), Zone::read('+01:00 ')]);
    }

    public function testCatalogInstallsHostZonesWithLocalIdentifiers(): void
    {
        $catalog = Zone::catalog();
        self::assertSame(['Africa/Abidjan', 1, false], $catalog[0]);
        self::assertContains('Asia/Tokyo', array_column($catalog, 0));
        self::assertSame(range(1, count($catalog)), array_column($catalog, 1));
    }

    public function testUtcIsTheOffsetZero(): void
    {
        self::assertTrue(Zone::utc()->universal());
    }

    public function testNamesListsTheZonesByLowerCaseName(): void
    {
        self::assertSame(['Asia/Tokyo', 'posixrules'], [Zone::names()['asia/tokyo'], Zone::names()['posixrules']]);
    }

    public function testUniversalTellsAZoneWithoutOffset(): void
    {
        self::assertSame([true, false, false], [Zone::named('SYSTEM')?->universal(), Zone::named('+01:00')?->universal(), Zone::named('UTC')?->universal()]);
    }

    public function testOffsetAtKeepsTheOffsetOf2037AfterIt(): void
    {
        $zone = Zone::named('Australia/Sydney');

        self::assertSame([39600, 39600], [$zone?->offsetAt(Calendar::epoch(2024, 1, 1, 0, 0, 0)), $zone?->offsetAt(Calendar::epoch(2050, 7, 1, 0, 0, 0))]);
    }

    public function testLocalAddsTheOffset(): void
    {
        self::assertSame(3600, Zone::named('CET')?->local(0));
    }

    public function testInstantTakesTheEarlierInstantOfARepeatedTimeAndTheChangeForASkippedOne(): void
    {
        $zone = Zone::named('Europe/Paris');

        self::assertSame([Calendar::epoch(2024, 10, 27, 0, 30, 0), Calendar::epoch(2024, 3, 31, 1, 0, 0), Calendar::epoch(2024, 1, 1, 0, 0, 0)], [$zone?->instant(Calendar::epoch(2024, 10, 27, 2, 30, 0)), $zone?->instant(Calendar::epoch(2024, 3, 31, 2, 30, 0)), $zone?->instant(Calendar::epoch(2024, 1, 1, 1, 0, 0))]);
    }
}
