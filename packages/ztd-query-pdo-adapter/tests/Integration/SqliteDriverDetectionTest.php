<?php

declare(strict_types=1);

namespace Tests\Integration;

use PDO;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Adapter\Pdo\ZtdPdo;
use ZtdQuery\Config\ZtdConfig;
use ZtdQuery\Platform\Sqlite\SqliteSessionFactory;

/**
 * Integration tests for ZtdPdo driver auto-detection with a real SQLite database.
 *
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

    public function testExplicitSessionFactoryInjection(): void
    {
        $pdo = new PDO('sqlite::memory:', null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);

        $factory = new SqliteSessionFactory();
        $ztdPdo = ZtdPdo::fromPdo($pdo, null, $factory);

        self::assertTrue($ztdPdo->isZtdEnabled());
    }

    public function testConstructorWithAutoDetection(): void
    {
        $ztdPdo = new ZtdPdo('sqlite::memory:', null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);

        self::assertTrue($ztdPdo->isZtdEnabled());
    }

    public function testConstructorWithExplicitFactory(): void
    {
        $factory = new SqliteSessionFactory();
        $ztdPdo = new ZtdPdo('sqlite::memory:', null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ], null, $factory);

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
