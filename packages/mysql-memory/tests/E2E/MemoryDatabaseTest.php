<?php

declare(strict_types=1);

namespace Tests\E2E;

use mysqli;
use mysqli_result;
use MySqlMemory\Server\MemoryDatabase;
use PDO;
use PDOException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Connections select and isolate each supported release through the public API.
 */
#[CoversClass(MemoryDatabase::class)]
#[Large]
final class MemoryDatabaseTest extends TestCase
{
    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function providerReleases(): iterable
    {
        foreach (['5.6.51', '5.7.44', '8.0.44', '8.1.0', '8.2.0', '8.3.0', '8.4.7', '9.0.1', '9.1.0'] as $version) {
            yield $version => [$version, !str_starts_with($version, '5.')];
        }
    }

    /**
     * Exercises the same instance with both native PHP drivers and release-specific grammar.
     */
    #[DataProvider('providerReleases')]
    public function testStartUsesTheSelectedVersionThroughBothDrivers(string $version, bool $commonTables): void
    {
        $database = MemoryDatabase::start($version, ['shop']);
        try {
            $pdo = new PDO($database->dsn('shop'), 'root', '');
            $mysqli = new mysqli($database->host, 'root', '', 'shop', $database->port);
            $pdoVersion = $pdo->query('SELECT VERSION()');
            $mysqliVersion = $mysqli->query('SELECT VERSION()');
            self::assertNotFalse($pdoVersion);
            self::assertInstanceOf(mysqli_result::class, $mysqliVersion);
            self::assertSame($version, $pdoVersion->fetchColumn());
            self::assertSame([$version], $mysqliVersion->fetch_row());
            $pdo->exec('CREATE TABLE items (id INT PRIMARY KEY, name VARCHAR(20))');
            $pdo->exec("INSERT INTO items VALUES (1, 'pen')");
            $items = $mysqli->query('SELECT * FROM items');
            self::assertInstanceOf(mysqli_result::class, $items);
            self::assertSame(['1', 'pen'], $items->fetch_row());
            try {
                $rows = $pdo->query('WITH c AS (SELECT 42 AS n) SELECT n FROM c');
                self::assertNotFalse($rows);
                $result = $rows->fetchColumn();
                self::assertTrue($commonTables);
                self::assertSame(42, $result);
            } catch (PDOException $error) {
                self::assertFalse($commonTables);
                self::assertSame(1064, $error->errorInfo[1] ?? null);
            }
            $mysqli->close();
        } finally {
            $database->stop();
        }
    }

    /**
     * Concurrent instances retain their own release and catalog.
     */
    public function testStartKeepsDifferentReleasesAndTheirDataIndependent(): void
    {
        $legacy = MemoryDatabase::start('5.7.44', ['shop']);
        $current = MemoryDatabase::start('9.1.0', ['shop']);
        try {
            $first = new PDO($legacy->dsn('shop'), 'root', '');
            $second = new PDO($current->dsn('shop'), 'root', '');
            $first->exec('CREATE TABLE legacy_only (id INT)');
            $firstVersion = $first->query('SELECT VERSION()');
            $secondVersion = $second->query('SELECT VERSION()');
            $tables = $second->query('SHOW TABLES');
            self::assertNotFalse($firstVersion);
            self::assertNotFalse($secondVersion);
            self::assertNotFalse($tables);
            self::assertSame('5.7.44', $firstVersion->fetchColumn());
            self::assertSame('9.1.0', $secondVersion->fetchColumn());
            self::assertSame([], $tables->fetchAll(PDO::FETCH_NUM));
            self::assertNotSame($legacy->port, $current->port);
        } finally {
            $legacy->stop();
            $current->stop();
        }
    }

    /**
     * Installations with the same MySQL release can have different time-zone catalogs.
     */
    public function testStartKeepsTimeZoneInstallationsIndependent(): void
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

    /**
     * No default release is substituted for an unavailable version.
     */
    public function testStartRejectsAnUnknownReleaseBeforeOpeningAConnection(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unsupported MySQL release: 8.4.999');
        MemoryDatabase::start('8.4.999');
    }
}
