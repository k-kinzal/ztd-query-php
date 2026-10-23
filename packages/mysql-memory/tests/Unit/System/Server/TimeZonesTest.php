<?php

declare(strict_types=1);

namespace Tests\Unit\System\Server;

use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use MySqlMemory\System\Server\TimeZones;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(TimeZones::class)]
#[Small]
final class TimeZonesTest extends TestCase
{
    public function testRowsOfAnUninitializedCatalogHasNoNamedZoneRows(): void
    {
        $session = (new Instance(timeZones: []))->connect();
        $zones = $session->query('SELECT * FROM mysql.time_zone')[0];
        $names = $session->query('SELECT * FROM mysql.time_zone_name')[0];
        self::assertInstanceOf(ResultSet::class, $zones);
        self::assertInstanceOf(ResultSet::class, $names);
        self::assertSame([], $zones->rows);
        self::assertSame([], $names->rows);
    }

    public function testRowsNumbersTheZonesAsTheServerDoes(): void
    {
        $s = (new Instance(timeZones: [['Africa/Abidjan', 1, false], ['UTC', 594, false], ['posixrules', 1197, false], ['right/UTC', 1791, true]]))->connect();
        $names = $s->query("SELECT * FROM mysql.time_zone_name WHERE Name IN ('Africa/Abidjan', 'UTC', 'posixrules', 'right/UTC')")[0];
        $zones = $s->query('SELECT * FROM mysql.time_zone WHERE Time_zone_id IN (1, 1197, 1791)')[0];

        self::assertInstanceOf(ResultSet::class, $names);
        self::assertInstanceOf(ResultSet::class, $zones);
        self::assertSame([['Africa/Abidjan', '1'], ['posixrules', '1197'], ['right/UTC', '1791'], ['UTC', '594']], $names->rows);
        self::assertSame([['1', 'N'], ['1197', 'N'], ['1791', 'Y']], $zones->rows);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function providerReleases(): iterable
    {
        yield '5.7' => ['5.7.44'];
        yield '8.4' => ['8.4.7'];
        yield '9.1' => ['9.1.0'];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('providerReleases')]
    public function testRowsUseTheInstallationIndependentlyOfTheMySqlRelease(string $version): void
    {
        $s = (new Instance($version, timeZones: [['UTC', 41, false], ['right/UTC', 7, true]]))->connect();
        $names = $s->query('SELECT * FROM mysql.time_zone_name ORDER BY Time_zone_id')[0];
        $count = $s->query("SELECT COUNT(*), SUM(Use_leap_seconds = 'Y') FROM mysql.time_zone")[0];
        self::assertInstanceOf(ResultSet::class, $names);
        self::assertInstanceOf(ResultSet::class, $count);
        self::assertSame([['right/UTC', '7'], ['UTC', '41']], $names->rows);
        self::assertSame([['2', '1']], $count->rows);
    }

    public function testInitialUsesTheHostInstallation(): void
    {
        self::assertContains('Asia/Tokyo', array_column(TimeZones::initial(), 0));
    }

    public function testRowsListTheNamesInTheOrderTheyAreNumberedOnMySql56(): void
    {
        $s = (new Instance('5.6.51', timeZones: [['America/Ensenada', 111, false], ['America/Fort_Nelson', 112, false], ['America/Fort_Wayne', 113, false], ['America/Fortaleza', 114, false]]))->connect();
        $names = $s->query('SELECT * FROM mysql.time_zone_name WHERE Time_zone_id BETWEEN 111 AND 114')[0];

        self::assertInstanceOf(ResultSet::class, $names);
        self::assertSame([['America/Ensenada', '111'], ['America/Fort_Nelson', '112'], ['America/Fort_Wayne', '113'], ['America/Fortaleza', '114']], $names->rows);
    }
}
