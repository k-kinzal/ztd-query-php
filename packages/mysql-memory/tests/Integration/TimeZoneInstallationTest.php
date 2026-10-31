<?php

declare(strict_types=1);

namespace Tests\Integration;

use MySqlMemory\Server\MemoryDatabase;
use PDO;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
#[Large]
final class TimeZoneInstallationTest extends TestCase
{
    public function testNamedTimeZoneConversionUsesOnlyTheInstallationOfItsDatabase(): void
    {
        $installed = MemoryDatabase::start('8.4.7', timeZones: [['Asia/Tokyo', 42, false]]);
        $empty = MemoryDatabase::start('8.4.7', timeZones: []);
        try {
            $pdo = new PDO($installed->dsn(), 'root', '');
            $other = new PDO($empty->dsn(), 'root', '');
            $names = $pdo->query('SELECT Name, Time_zone_id FROM mysql.time_zone_name');
            $converted = $pdo->query("SELECT CONVERT_TZ('2024-01-01 00:00:00', '+00:00', 'Asia/Tokyo')");
            $unavailable = $other->query("SELECT CONVERT_TZ('2024-01-01 00:00:00', '+00:00', 'Asia/Tokyo')");
            self::assertNotFalse($names);
            self::assertNotFalse($converted);
            self::assertNotFalse($unavailable);
            self::assertSame([['Asia/Tokyo', 42]], $names->fetchAll(PDO::FETCH_NUM));
            self::assertSame('2024-01-01 09:00:00', $converted->fetchColumn());
            self::assertNull($unavailable->fetchColumn());
        } finally {
            $installed->stop();
            $empty->stop();
        }
    }

}
