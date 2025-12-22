<?php

declare(strict_types=1);

namespace Tests\Integration\Sqlite;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Adapter\Pdo\ZtdPdo;

/**
 * @requires extension pdo_sqlite
 */
#[CoversNothing]
#[Large]
final class CreateTableTest extends TestCase
{
    public function testCreateTableAndInsert(): void
    {
        $rawPdo = new \PDO('sqlite::memory:', null, null, [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
        ]);

        $ztdPdo = ZtdPdo::fromPdo($rawPdo, null);

        $ztdPdo->exec("CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT NOT NULL)");
        $ztdPdo->exec("INSERT INTO users (id, name) VALUES (1, 'Alice')");

        $stmt = $ztdPdo->query("SELECT * FROM users ORDER BY id");
        self::assertNotFalse($stmt);
        /** @var list<array<string, mixed>> $ztdRows */
        $ztdRows = $stmt->fetchAll();
        self::assertCount(1, $ztdRows);
        self::assertEquals(1, $ztdRows[0]['id']);
        self::assertSame('Alice', $ztdRows[0]['name']);
    }

    public function testCreateTableIfNotExists(): void
    {
        $rawPdo = new \PDO('sqlite::memory:', null, null, [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
        ]);

        $ztdPdo = ZtdPdo::fromPdo($rawPdo, null);

        $ztdPdo->exec("CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT NOT NULL)");
        $ztdPdo->exec("CREATE TABLE IF NOT EXISTS users (id INTEGER PRIMARY KEY, name TEXT NOT NULL)");
        $ztdPdo->exec("INSERT INTO users (id, name) VALUES (1, 'Test')");

        $stmt = $ztdPdo->query("SELECT * FROM users");
        self::assertNotFalse($stmt);
        /** @var list<array<string, mixed>> $ztdRows */
        $ztdRows = $stmt->fetchAll();
        self::assertCount(1, $ztdRows);
    }

    public function testCreateTableDoesNotModifyPhysicalDatabase(): void
    {
        $rawPdo = new \PDO('sqlite::memory:', null, null, [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
        ]);

        $ztdPdo = ZtdPdo::fromPdo($rawPdo, null);

        $ztdPdo->exec("CREATE TABLE virtual_table (id INTEGER PRIMARY KEY, name TEXT NOT NULL)");

        $stmt = $rawPdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='virtual_table'");
        self::assertNotFalse($stmt);
        $rows = $stmt->fetchAll();
        self::assertCount(0, $rows);
    }
}
