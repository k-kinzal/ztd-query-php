<?php

declare(strict_types=1);

namespace Tests\Integration\Sqlite;

use PDO;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Adapter\Pdo\ZtdPdo;
use ZtdQuery\Connection\StatementInterface;

/**
 * @requires extension pdo_sqlite
 *
 * @phpstan-import-type Row from StatementInterface
 */
#[CoversNothing]
#[Large]
final class UpdateBasicTest extends TestCase
{
    public function testUpdateSingleRow(): void
    {
        $rawPdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
        $rawPdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT NOT NULL, age INTEGER NOT NULL)');
        $rawPdo->exec("INSERT INTO users (id, name, age) VALUES (1, 'Alice', 30), (2, 'Bob', 25), (3, 'Charlie', 35)");
        $ztdPdo = ZtdPdo::fromPdo($rawPdo);
        $ztdPdo->exec("INSERT INTO users (id, name, age) VALUES (1, 'Alice', 30), (2, 'Bob', 25), (3, 'Charlie', 35)");

        $rawPdo->exec("UPDATE users SET name = 'Alice Updated' WHERE id = 1");
        $ztdPdo->exec("UPDATE users SET name = 'Alice Updated' WHERE id = 1");

        $stmt = $rawPdo->query('SELECT * FROM users ORDER BY id');
        self::assertNotFalse($stmt);
        /** @var list<Row> $rawRows */
        $rawRows = $stmt->fetchAll();
        $stmt = $ztdPdo->query('SELECT * FROM users ORDER BY id');
        self::assertNotFalse($stmt);
        /** @var list<Row> $ztdRows */
        $ztdRows = $stmt->fetchAll();
        self::assertSame($rawRows, $ztdRows);
    }

    public function testUpdateMultipleColumns(): void
    {
        $rawPdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
        $rawPdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT NOT NULL, age INTEGER NOT NULL)');
        $rawPdo->exec("INSERT INTO users (id, name, age) VALUES (1, 'Alice', 30), (2, 'Bob', 25), (3, 'Charlie', 35)");
        $ztdPdo = ZtdPdo::fromPdo($rawPdo);
        $ztdPdo->exec("INSERT INTO users (id, name, age) VALUES (1, 'Alice', 30), (2, 'Bob', 25), (3, 'Charlie', 35)");

        $rawPdo->exec("UPDATE users SET name = 'Alice Updated', age = 31 WHERE id = 1");
        $ztdPdo->exec("UPDATE users SET name = 'Alice Updated', age = 31 WHERE id = 1");

        $stmt = $rawPdo->query('SELECT * FROM users ORDER BY id');
        self::assertNotFalse($stmt);
        /** @var list<Row> $rawRows */
        $rawRows = $stmt->fetchAll();
        $stmt = $ztdPdo->query('SELECT * FROM users ORDER BY id');
        self::assertNotFalse($stmt);
        /** @var list<Row> $ztdRows */
        $ztdRows = $stmt->fetchAll();
        self::assertSame($rawRows, $ztdRows);
    }

    public function testUpdateDoesNotModifyPhysicalDatabase(): void
    {
        $rawPdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
        $rawPdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT NOT NULL, age INTEGER NOT NULL)');
        $rawPdo->exec("INSERT INTO users (id, name, age) VALUES (1, 'Alice', 30), (2, 'Bob', 25), (3, 'Charlie', 35)");
        $ztdPdo = ZtdPdo::fromPdo($rawPdo);
        $ztdPdo->exec("INSERT INTO users (id, name, age) VALUES (1, 'Alice', 30), (2, 'Bob', 25), (3, 'Charlie', 35)");

        $ztdPdo->exec("UPDATE users SET name = 'Modified' WHERE id = 1");

        $stmt = $rawPdo->query('SELECT name FROM users WHERE id = 1');
        self::assertNotFalse($stmt);
        /** @var list<Row> $rawRows */
        $rawRows = $stmt->fetchAll();
        self::assertSame('Alice', $rawRows[0]['name']);
    }

    public function testPrimaryKeyChangesAndColumnSwapsRetainOriginalRowIdentity(): void
    {
        $rawPdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
        $rawPdo->exec('CREATE TABLE pairs (id INTEGER PRIMARY KEY, left_value TEXT, right_value TEXT)');
        $ztdPdo = ZtdPdo::fromPdo($rawPdo);
        $ztdPdo->exec("INSERT INTO pairs VALUES (1, 'left', 'right'), (2, 'second', 'row')");

        self::assertSame(1, $ztdPdo->exec('UPDATE pairs SET id = 10, left_value = right_value, right_value = left_value WHERE id = 1'));
        $statement = $ztdPdo->query('SELECT * FROM pairs ORDER BY id');
        self::assertNotFalse($statement);
        self::assertSame([
            ['id' => 2, 'left_value' => 'second', 'right_value' => 'row'],
            ['id' => 10, 'left_value' => 'right', 'right_value' => 'left'],
        ], $statement->fetchAll());

        self::assertSame(2, $ztdPdo->exec('DELETE FROM pairs'));
        $statement = $ztdPdo->query('SELECT * FROM pairs');
        self::assertNotFalse($statement);
        self::assertSame([], $statement->fetchAll());
    }

    public function testUpdateWithGroupedInSubquery(): void
    {
        $rawPdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
        $rawPdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT, tier TEXT)');
        $rawPdo->exec('CREATE TABLE orders (id INTEGER PRIMARY KEY, user_id INTEGER, total REAL, status TEXT)');
        $rawPdo->exec("INSERT INTO users VALUES (1, 'Alice', 'standard'), (2, 'Bob', 'standard')");
        $rawPdo->exec("INSERT INTO orders VALUES (1, 1, 500, 'completed'), (2, 1, 300, 'completed'), (3, 2, 100, 'completed')");
        $ztdPdo = ZtdPdo::fromPdo($rawPdo);
        $ztdPdo->exec("INSERT INTO users VALUES (1, 'Alice', 'standard'), (2, 'Bob', 'standard')");
        $ztdPdo->exec("INSERT INTO orders VALUES (1, 1, 500, 'completed'), (2, 1, 300, 'completed'), (3, 2, 100, 'completed')");

        $sql = "UPDATE users SET tier = 'premium' WHERE id IN (SELECT user_id FROM orders WHERE status = 'completed' GROUP BY user_id HAVING SUM(total) > 400)";
        self::assertSame($rawPdo->exec($sql), $ztdPdo->exec($sql));

        $raw = $rawPdo->query('SELECT * FROM users ORDER BY id');
        $shadow = $ztdPdo->query('SELECT * FROM users ORDER BY id');
        self::assertNotFalse($raw);
        self::assertNotFalse($shadow);
        self::assertSame($raw->fetchAll(), $shadow->fetchAll());
    }
}
