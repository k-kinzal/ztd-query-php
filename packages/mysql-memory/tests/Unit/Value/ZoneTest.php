<?php

declare(strict_types=1);

namespace Tests\Unit\Value;

use MySqlMemory\Value\Calendar;
use MySqlMemory\Value\Zone;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\GrammarRelease;

#[CoversClass(Zone::class)]
#[Small]
final class ZoneTest extends TestCase
{
    public function testNamedReadsOffsetsSystemAndTheNamesOfTheTables(): void
    {
        self::assertSame(['+05:30', '-00:30', 'SYSTEM', 'Europe/Paris', 'UTC', 'posix/Europe/Paris'], [Zone::named('+5:30')?->name, Zone::named('-0:30')?->name, Zone::named('system')?->name, Zone::named('europe/paris')?->name, Zone::named('utc ')?->name, Zone::named('POSIX/europe/paris')?->name]);
    }

    public function testNamedReadsTheNamesOfTheTablesOfTheRelease(): void
    {
        self::assertSame(['Europe/Kyiv', null, null, null], [Zone::named('Europe/Kyiv')?->name, Zone::named('Europe/Kyiv', GrammarRelease::MySql5651)?->name, Zone::named('America/Coyhaique', GrammarRelease::MySql5744)?->name, Zone::named('posix/posixrules')?->name]);
    }

    public function testReadRefusesWhatNamesNoZone(): void
    {
        self::assertSame([null, null, null, null], [Zone::read('+14:01'), Zone::read('-14:00'), Zone::read(' UTC'), Zone::read('+01:00 ')]);
    }

    public function testCatalogNumbersTheZonesAsTheTablesOfTheReleaseDo(): void
    {
        self::assertSame([['Africa/Abidjan', 1, false], ['posixrules', 1197, false], ['right/UTC', 1791, true], 1795, ['posixrules', 1218, false], 1826, 1795], [Zone::catalog(GrammarRelease::MySql847)[0], Zone::catalog(GrammarRelease::MySql847)[1196], Zone::catalog(GrammarRelease::MySql847)[1790], count(Zone::catalog(GrammarRelease::MySql847)), Zone::catalog(GrammarRelease::MySql5651)[1217], count(Zone::catalog(GrammarRelease::MySql5651)), count(Zone::catalog(GrammarRelease::MySql830))]);
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
