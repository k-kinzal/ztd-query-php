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
final class SelectWhereTest extends TestCase
{
    public function testWhereEquality(): void
    {
        $rawPdo = new \PDO('sqlite::memory:', null, null, [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION, \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC]);
        $rawPdo->exec("CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT NOT NULL, age INTEGER NOT NULL)");
        $rawPdo->exec("INSERT INTO users (id, name, age) VALUES (1, 'Alice', 30), (2, 'Bob', 25), (3, 'Charlie', 35)");
        $ztdPdo = ZtdPdo::fromPdo($rawPdo);
        $ztdPdo->exec("INSERT INTO users (id, name, age) VALUES (1, 'Alice', 30), (2, 'Bob', 25), (3, 'Charlie', 35)");

        $stmt = $rawPdo->query("SELECT * FROM users WHERE id = 1");
        self::assertNotFalse($stmt);
        /** @var list<array<string, mixed>> $rawRows */
        $rawRows = $stmt->fetchAll();
        $stmt = $ztdPdo->query("SELECT * FROM users WHERE id = 1");
        self::assertNotFalse($stmt);
        /** @var list<array<string, mixed>> $ztdRows */
        $ztdRows = $stmt->fetchAll();
        self::assertSame($rawRows, $ztdRows);
    }

    public function testWhereComparison(): void
    {
        $rawPdo = new \PDO('sqlite::memory:', null, null, [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION, \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC]);
        $rawPdo->exec("CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT NOT NULL, age INTEGER NOT NULL)");
        $rawPdo->exec("INSERT INTO users (id, name, age) VALUES (1, 'Alice', 30), (2, 'Bob', 25), (3, 'Charlie', 35)");
        $ztdPdo = ZtdPdo::fromPdo($rawPdo);
        $ztdPdo->exec("INSERT INTO users (id, name, age) VALUES (1, 'Alice', 30), (2, 'Bob', 25), (3, 'Charlie', 35)");

        $stmt = $rawPdo->query("SELECT * FROM users WHERE age > 28 ORDER BY id");
        self::assertNotFalse($stmt);
        /** @var list<array<string, mixed>> $rawRows */
        $rawRows = $stmt->fetchAll();
        $stmt = $ztdPdo->query("SELECT * FROM users WHERE age > 28 ORDER BY id");
        self::assertNotFalse($stmt);
        /** @var list<array<string, mixed>> $ztdRows */
        $ztdRows = $stmt->fetchAll();
        self::assertSame($rawRows, $ztdRows);
    }

    public function testWhereAnd(): void
    {
        $rawPdo = new \PDO('sqlite::memory:', null, null, [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION, \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC]);
        $rawPdo->exec("CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT NOT NULL, age INTEGER NOT NULL)");
        $rawPdo->exec("INSERT INTO users (id, name, age) VALUES (1, 'Alice', 30), (2, 'Bob', 25), (3, 'Charlie', 35)");
        $ztdPdo = ZtdPdo::fromPdo($rawPdo);
        $ztdPdo->exec("INSERT INTO users (id, name, age) VALUES (1, 'Alice', 30), (2, 'Bob', 25), (3, 'Charlie', 35)");

        $stmt = $rawPdo->query("SELECT * FROM users WHERE age > 20 AND name = 'Bob'");
        self::assertNotFalse($stmt);
        /** @var list<array<string, mixed>> $rawRows */
        $rawRows = $stmt->fetchAll();
        $stmt = $ztdPdo->query("SELECT * FROM users WHERE age > 20 AND name = 'Bob'");
        self::assertNotFalse($stmt);
        /** @var list<array<string, mixed>> $ztdRows */
        $ztdRows = $stmt->fetchAll();
        self::assertSame($rawRows, $ztdRows);
    }

    public function testWhereOr(): void
    {
        $rawPdo = new \PDO('sqlite::memory:', null, null, [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION, \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC]);
        $rawPdo->exec("CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT NOT NULL, age INTEGER NOT NULL)");
        $rawPdo->exec("INSERT INTO users (id, name, age) VALUES (1, 'Alice', 30), (2, 'Bob', 25), (3, 'Charlie', 35)");
        $ztdPdo = ZtdPdo::fromPdo($rawPdo);
        $ztdPdo->exec("INSERT INTO users (id, name, age) VALUES (1, 'Alice', 30), (2, 'Bob', 25), (3, 'Charlie', 35)");

        $stmt = $rawPdo->query("SELECT * FROM users WHERE name = 'Alice' OR name = 'Charlie' ORDER BY id");
        self::assertNotFalse($stmt);
        /** @var list<array<string, mixed>> $rawRows */
        $rawRows = $stmt->fetchAll();
        $stmt = $ztdPdo->query("SELECT * FROM users WHERE name = 'Alice' OR name = 'Charlie' ORDER BY id");
        self::assertNotFalse($stmt);
        /** @var list<array<string, mixed>> $ztdRows */
        $ztdRows = $stmt->fetchAll();
        self::assertSame($rawRows, $ztdRows);
    }

    public function testWhereIn(): void
    {
        $rawPdo = new \PDO('sqlite::memory:', null, null, [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION, \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC]);
        $rawPdo->exec("CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT NOT NULL, age INTEGER NOT NULL)");
        $rawPdo->exec("INSERT INTO users (id, name, age) VALUES (1, 'Alice', 30), (2, 'Bob', 25), (3, 'Charlie', 35)");
        $ztdPdo = ZtdPdo::fromPdo($rawPdo);
        $ztdPdo->exec("INSERT INTO users (id, name, age) VALUES (1, 'Alice', 30), (2, 'Bob', 25), (3, 'Charlie', 35)");

        $stmt = $rawPdo->query("SELECT * FROM users WHERE id IN (1, 3) ORDER BY id");
        self::assertNotFalse($stmt);
        /** @var list<array<string, mixed>> $rawRows */
        $rawRows = $stmt->fetchAll();
        $stmt = $ztdPdo->query("SELECT * FROM users WHERE id IN (1, 3) ORDER BY id");
        self::assertNotFalse($stmt);
        /** @var list<array<string, mixed>> $ztdRows */
        $ztdRows = $stmt->fetchAll();
        self::assertSame($rawRows, $ztdRows);
    }

    public function testWhereBetween(): void
    {
        $rawPdo = new \PDO('sqlite::memory:', null, null, [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION, \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC]);
        $rawPdo->exec("CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT NOT NULL, age INTEGER NOT NULL)");
        $rawPdo->exec("INSERT INTO users (id, name, age) VALUES (1, 'Alice', 30), (2, 'Bob', 25), (3, 'Charlie', 35)");
        $ztdPdo = ZtdPdo::fromPdo($rawPdo);
        $ztdPdo->exec("INSERT INTO users (id, name, age) VALUES (1, 'Alice', 30), (2, 'Bob', 25), (3, 'Charlie', 35)");

        $stmt = $rawPdo->query("SELECT * FROM users WHERE age BETWEEN 25 AND 32 ORDER BY id");
        self::assertNotFalse($stmt);
        /** @var list<array<string, mixed>> $rawRows */
        $rawRows = $stmt->fetchAll();
        $stmt = $ztdPdo->query("SELECT * FROM users WHERE age BETWEEN 25 AND 32 ORDER BY id");
        self::assertNotFalse($stmt);
        /** @var list<array<string, mixed>> $ztdRows */
        $ztdRows = $stmt->fetchAll();
        self::assertSame($rawRows, $ztdRows);
    }

    public function testWhereLike(): void
    {
        $rawPdo = new \PDO('sqlite::memory:', null, null, [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION, \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC]);
        $rawPdo->exec("CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT NOT NULL, age INTEGER NOT NULL)");
        $rawPdo->exec("INSERT INTO users (id, name, age) VALUES (1, 'Alice', 30), (2, 'Bob', 25), (3, 'Charlie', 35)");
        $ztdPdo = ZtdPdo::fromPdo($rawPdo);
        $ztdPdo->exec("INSERT INTO users (id, name, age) VALUES (1, 'Alice', 30), (2, 'Bob', 25), (3, 'Charlie', 35)");

        $stmt = $rawPdo->query("SELECT * FROM users WHERE name LIKE 'A%'");
        self::assertNotFalse($stmt);
        /** @var list<array<string, mixed>> $rawRows */
        $rawRows = $stmt->fetchAll();
        $stmt = $ztdPdo->query("SELECT * FROM users WHERE name LIKE 'A%'");
        self::assertNotFalse($stmt);
        /** @var list<array<string, mixed>> $ztdRows */
        $ztdRows = $stmt->fetchAll();
        self::assertSame($rawRows, $ztdRows);
    }

    public function testWhereIsNull(): void
    {
        $rawPdo = new \PDO('sqlite::memory:', null, null, [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION, \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC]);
        $rawPdo->exec("CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT NOT NULL, age INTEGER NOT NULL)");
        $rawPdo->exec("INSERT INTO users (id, name, age) VALUES (1, 'Alice', 30), (2, 'Bob', 25), (3, 'Charlie', 35)");
        $ztdPdo = ZtdPdo::fromPdo($rawPdo);
        $ztdPdo->exec("INSERT INTO users (id, name, age) VALUES (1, 'Alice', 30), (2, 'Bob', 25), (3, 'Charlie', 35)");

        $stmt = $rawPdo->query("SELECT * FROM users WHERE name IS NOT NULL ORDER BY id");
        self::assertNotFalse($stmt);
        /** @var list<array<string, mixed>> $rawRows */
        $rawRows = $stmt->fetchAll();
        $stmt = $ztdPdo->query("SELECT * FROM users WHERE name IS NOT NULL ORDER BY id");
        self::assertNotFalse($stmt);
        /** @var list<array<string, mixed>> $ztdRows */
        $ztdRows = $stmt->fetchAll();
        self::assertSame($rawRows, $ztdRows);
    }
}
