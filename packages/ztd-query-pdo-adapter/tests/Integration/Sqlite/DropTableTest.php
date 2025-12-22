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
final class DropTableTest extends TestCase
{
    public function testDropTableRemovesFromRegistry(): void
    {
        $rawPdo = new \PDO('sqlite::memory:', null, null, [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
        ]);

        $rawPdo->exec("CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT NOT NULL)");
        $rawPdo->exec("INSERT INTO users (id, name) VALUES (1, 'Alice')");

        $ztdPdo = ZtdPdo::fromPdo($rawPdo);

        $ztdPdo->exec("INSERT INTO users (id, name) VALUES (1, 'Alice')");

        $stmt = $ztdPdo->query("SELECT * FROM users");
        self::assertNotFalse($stmt);
        /** @var list<array<string, mixed>> $rows */
        $rows = $stmt->fetchAll();
        self::assertCount(1, $rows);

        $ztdPdo->exec("DROP TABLE users");

        $stmt = $rawPdo->query("SELECT * FROM users");
        self::assertNotFalse($stmt);
        /** @var list<array<string, mixed>> $rawRows */
        $rawRows = $stmt->fetchAll();
        self::assertCount(1, $rawRows);
    }

    public function testDropTableIfExists(): void
    {
        $rawPdo = new \PDO('sqlite::memory:', null, null, [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
        ]);

        $ztdPdo = ZtdPdo::fromPdo($rawPdo, null);

        $ztdPdo->exec("DROP TABLE IF EXISTS users");
        self::addToAssertionCount(1);
    }
}
