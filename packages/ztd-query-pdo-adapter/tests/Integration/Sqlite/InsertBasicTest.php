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
final class InsertBasicTest extends TestCase
{
    public function testSingleRowInsert(): void
    {
        $rawPdo = new \PDO('sqlite::memory:', null, null, [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
        ]);
        $rawPdo->exec("CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT NOT NULL, age INTEGER NOT NULL)");

        $ztdPdo = ZtdPdo::fromPdo($rawPdo);

        $rawPdo->exec("INSERT INTO users (id, name, age) VALUES (1, 'Alice', 30)");
        $ztdPdo->exec("INSERT INTO users (id, name, age) VALUES (1, 'Alice', 30)");

        $stmt = $rawPdo->query("SELECT * FROM users ORDER BY id");
        self::assertNotFalse($stmt);
        /** @var list<array<string, mixed>> $rawRows */
        $rawRows = $stmt->fetchAll();
        $stmt = $ztdPdo->query("SELECT * FROM users ORDER BY id");
        self::assertNotFalse($stmt);
        /** @var list<array<string, mixed>> $ztdRows */
        $ztdRows = $stmt->fetchAll();

        self::assertSame($rawRows, $ztdRows);
    }

    public function testMultiRowInsert(): void
    {
        $rawPdo = new \PDO('sqlite::memory:', null, null, [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
        ]);
        $rawPdo->exec("CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT NOT NULL, age INTEGER NOT NULL)");

        $ztdPdo = ZtdPdo::fromPdo($rawPdo);

        $rawPdo->exec("INSERT INTO users (id, name, age) VALUES (1, 'Alice', 30), (2, 'Bob', 25)");
        $ztdPdo->exec("INSERT INTO users (id, name, age) VALUES (1, 'Alice', 30), (2, 'Bob', 25)");

        $stmt = $rawPdo->query("SELECT * FROM users ORDER BY id");
        self::assertNotFalse($stmt);
        /** @var list<array<string, mixed>> $rawRows */
        $rawRows = $stmt->fetchAll();
        $stmt = $ztdPdo->query("SELECT * FROM users ORDER BY id");
        self::assertNotFalse($stmt);
        /** @var list<array<string, mixed>> $ztdRows */
        $ztdRows = $stmt->fetchAll();

        self::assertSame($rawRows, $ztdRows);
    }

    public function testInsertDoesNotModifyPhysicalDatabase(): void
    {
        $rawPdo = new \PDO('sqlite::memory:', null, null, [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
        ]);
        $rawPdo->exec("CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT NOT NULL, age INTEGER NOT NULL)");

        $ztdPdo = ZtdPdo::fromPdo($rawPdo);

        $ztdPdo->exec("INSERT INTO users (id, name, age) VALUES (1, 'Alice', 30)");

        $stmt = $rawPdo->query("SELECT * FROM users");
        self::assertNotFalse($stmt);
        /** @var list<array<string, mixed>> $rawRows */
        $rawRows = $stmt->fetchAll();
        self::assertCount(0, $rawRows);
    }
}
