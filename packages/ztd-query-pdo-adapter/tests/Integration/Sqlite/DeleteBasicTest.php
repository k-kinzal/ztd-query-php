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
final class DeleteBasicTest extends TestCase
{
    public function testDeleteSingleRow(): void
    {
        $rawPdo = new \PDO('sqlite::memory:', null, null, [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
        ]);
        $rawPdo->exec("CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT NOT NULL, age INTEGER NOT NULL)");
        $rawPdo->exec("INSERT INTO users (id, name, age) VALUES (1, 'Alice', 30), (2, 'Bob', 25), (3, 'Charlie', 35)");

        $ztdPdo = ZtdPdo::fromPdo($rawPdo);
        $ztdPdo->exec("INSERT INTO users (id, name, age) VALUES (1, 'Alice', 30), (2, 'Bob', 25), (3, 'Charlie', 35)");

        $rawPdo->exec("DELETE FROM users WHERE id = 1");
        $ztdPdo->exec("DELETE FROM users WHERE id = 1");

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

    public function testDeleteAllRowsWithCondition(): void
    {
        $rawPdo = new \PDO('sqlite::memory:', null, null, [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
        ]);
        $rawPdo->exec("CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT NOT NULL, age INTEGER NOT NULL)");
        $rawPdo->exec("INSERT INTO users (id, name, age) VALUES (1, 'Alice', 30), (2, 'Bob', 25), (3, 'Charlie', 35)");

        $ztdPdo = ZtdPdo::fromPdo($rawPdo);
        $ztdPdo->exec("INSERT INTO users (id, name, age) VALUES (1, 'Alice', 30), (2, 'Bob', 25), (3, 'Charlie', 35)");

        $rawPdo->exec("DELETE FROM users WHERE 1=1");
        $ztdPdo->exec("DELETE FROM users WHERE 1=1");

        $stmt = $rawPdo->query("SELECT * FROM users");
        self::assertNotFalse($stmt);
        /** @var list<array<string, mixed>> $rawRows */
        $rawRows = $stmt->fetchAll();
        $stmt = $ztdPdo->query("SELECT * FROM users");
        self::assertNotFalse($stmt);
        /** @var list<array<string, mixed>> $ztdRows */
        $ztdRows = $stmt->fetchAll();

        self::assertSame($rawRows, $ztdRows);
    }

    public function testDeleteDoesNotModifyPhysicalDatabase(): void
    {
        $rawPdo = new \PDO('sqlite::memory:', null, null, [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
        ]);
        $rawPdo->exec("CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT NOT NULL, age INTEGER NOT NULL)");
        $rawPdo->exec("INSERT INTO users (id, name, age) VALUES (1, 'Alice', 30), (2, 'Bob', 25), (3, 'Charlie', 35)");

        $ztdPdo = ZtdPdo::fromPdo($rawPdo);
        $ztdPdo->exec("INSERT INTO users (id, name, age) VALUES (1, 'Alice', 30), (2, 'Bob', 25), (3, 'Charlie', 35)");

        $ztdPdo->exec("DELETE FROM users WHERE id = 1");

        $stmt = $rawPdo->query("SELECT * FROM users");
        self::assertNotFalse($stmt);
        /** @var list<array<string, mixed>> $rawRows */
        $rawRows = $stmt->fetchAll();
        self::assertCount(3, $rawRows);
    }

    public function testDeleteWithCondition(): void
    {
        $rawPdo = new \PDO('sqlite::memory:', null, null, [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
        ]);
        $rawPdo->exec("CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT NOT NULL, age INTEGER NOT NULL)");
        $rawPdo->exec("INSERT INTO users (id, name, age) VALUES (1, 'Alice', 30), (2, 'Bob', 25), (3, 'Charlie', 35)");

        $ztdPdo = ZtdPdo::fromPdo($rawPdo);
        $ztdPdo->exec("INSERT INTO users (id, name, age) VALUES (1, 'Alice', 30), (2, 'Bob', 25), (3, 'Charlie', 35)");

        $rawPdo->exec("DELETE FROM users WHERE age > 28");
        $ztdPdo->exec("DELETE FROM users WHERE age > 28");

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

    public function testDeleteWithGroupedInSubquery(): void
    {
        $rawPdo = new \PDO('sqlite::memory:', null, null, [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
        ]);
        $rawPdo->exec('CREATE TABLE customers (id INTEGER PRIMARY KEY, name TEXT)');
        $rawPdo->exec('CREATE TABLE orders (id INTEGER PRIMARY KEY, customer_id INTEGER, amount REAL, status TEXT)');
        $rawPdo->exec("INSERT INTO customers VALUES (1, 'Alice'), (2, 'Bob')");
        $rawPdo->exec("INSERT INTO orders VALUES (1, 1, 50, 'completed'), (2, 1, 75, 'completed'), (3, 2, 10, 'completed')");
        $ztdPdo = ZtdPdo::fromPdo($rawPdo);
        $ztdPdo->exec("INSERT INTO customers VALUES (1, 'Alice'), (2, 'Bob')");
        $ztdPdo->exec("INSERT INTO orders VALUES (1, 1, 50, 'completed'), (2, 1, 75, 'completed'), (3, 2, 10, 'completed')");

        $sql = "DELETE FROM customers WHERE id IN (SELECT customer_id FROM orders WHERE status = 'completed' GROUP BY customer_id HAVING SUM(amount) < 50)";
        self::assertSame($rawPdo->exec($sql), $ztdPdo->exec($sql));

        $raw = $rawPdo->query('SELECT * FROM customers ORDER BY id');
        $shadow = $ztdPdo->query('SELECT * FROM customers ORDER BY id');
        self::assertNotFalse($raw);
        self::assertNotFalse($shadow);
        self::assertSame($raw->fetchAll(), $shadow->fetchAll());
    }
}
