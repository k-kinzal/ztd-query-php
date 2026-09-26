<?php

declare(strict_types=1);

namespace Tests\Integration;

use PDO;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Adapter\Pdo\ZtdPdo;
use ZtdQuery\Config\ZtdConfig;
use ZtdQuery\Platform\Sqlite\SqlitePlatform;

/**
 * @requires extension pdo_sqlite
 */
#[CoversNothing]
#[Large]
final class SqliteDriverDetectionTest extends TestCase
{
    public function testAutoDetectionCreatesSqliteSession(): void
    {
        $pdo = new PDO('sqlite::memory:', null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);

        $ztdPdo = ZtdPdo::fromPdo($pdo);

        self::assertTrue($ztdPdo->isZtdEnabled());
    }

    public function testExplicitPlatformInjection(): void
    {
        $pdo = new PDO('sqlite::memory:', null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);

        $platform = new SqlitePlatform();
        $ztdPdo = ZtdPdo::fromPdo($pdo, null, $platform);

        self::assertTrue($ztdPdo->isZtdEnabled());
    }

    public function testAConnectionOpenedByDsnReadsItsPlatformOffTheDriver(): void
    {
        $ztdPdo = new ZtdPdo('sqlite::memory:', null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);

        self::assertTrue($ztdPdo->isZtdEnabled());
    }

    public function testAConnectionOpenedByDsnUsesThePlatformItIsGiven(): void
    {
        $platform = new SqlitePlatform();
        $ztdPdo = new ZtdPdo('sqlite::memory:', null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ], null, $platform);

        self::assertTrue($ztdPdo->isZtdEnabled());
    }

    public function testCustomConfigPassedToSession(): void
    {
        $config = ZtdConfig::default();
        $pdo = new PDO('sqlite::memory:', null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);

        $ztdPdo = ZtdPdo::fromPdo($pdo, $config);

        self::assertTrue($ztdPdo->isZtdEnabled());
    }
}
