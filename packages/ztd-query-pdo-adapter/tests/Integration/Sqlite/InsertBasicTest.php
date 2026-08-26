<?php

declare(strict_types=1);

namespace Tests\Integration\Sqlite;

use PDO;
use PDOStatement;
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
        $rawPdo = new PDO('sqlite::memory:', null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $rawPdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT NOT NULL, age INTEGER NOT NULL)');

        $ztdPdo = ZtdPdo::fromPdo($rawPdo);

        $rawPdo->exec("INSERT INTO users (id, name, age) VALUES (1, 'Alice', 30)");
        $ztdPdo->exec("INSERT INTO users (id, name, age) VALUES (1, 'Alice', 30)");

        $stmt = $rawPdo->query('SELECT * FROM users ORDER BY id');
        self::assertNotFalse($stmt);
        /** @var list<array<string, mixed>> $rawRows */
        $rawRows = $stmt->fetchAll();
        $stmt = $ztdPdo->query('SELECT * FROM users ORDER BY id');
        self::assertNotFalse($stmt);
        /** @var list<array<string, mixed>> $ztdRows */
        $ztdRows = $stmt->fetchAll();

        self::assertSame($rawRows, $ztdRows);
    }

    public function testMultiRowInsert(): void
    {
        $rawPdo = new PDO('sqlite::memory:', null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $rawPdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT NOT NULL, age INTEGER NOT NULL)');

        $ztdPdo = ZtdPdo::fromPdo($rawPdo);

        $rawPdo->exec("INSERT INTO users (id, name, age) VALUES (1, 'Alice', 30), (2, 'Bob', 25)");
        $ztdPdo->exec("INSERT INTO users (id, name, age) VALUES (1, 'Alice', 30), (2, 'Bob', 25)");

        $stmt = $rawPdo->query('SELECT * FROM users ORDER BY id');
        self::assertNotFalse($stmt);
        /** @var list<array<string, mixed>> $rawRows */
        $rawRows = $stmt->fetchAll();
        $stmt = $ztdPdo->query('SELECT * FROM users ORDER BY id');
        self::assertNotFalse($stmt);
        /** @var list<array<string, mixed>> $ztdRows */
        $ztdRows = $stmt->fetchAll();

        self::assertSame($rawRows, $ztdRows);
    }

    public function testInsertDoesNotModifyPhysicalDatabase(): void
    {
        $rawPdo = new PDO('sqlite::memory:', null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $rawPdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT NOT NULL, age INTEGER NOT NULL)');

        $ztdPdo = ZtdPdo::fromPdo($rawPdo);

        $ztdPdo->exec("INSERT INTO users (id, name, age) VALUES (1, 'Alice', 30)");

        $stmt = $rawPdo->query('SELECT * FROM users');
        self::assertNotFalse($stmt);
        /** @var list<array<string, mixed>> $rawRows */
        $rawRows = $stmt->fetchAll();
        self::assertCount(0, $rawRows);
    }

    public function testOmittedColumnsAndDefaultValuesMatchSqlite(): void
    {
        $rawPdo = new PDO('sqlite::memory:', null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $rawPdo->exec("CREATE TABLE settings (id INTEGER DEFAULT 7, status TEXT DEFAULT 'active', note TEXT)");
        $ztdPdo = ZtdPdo::fromPdo($rawPdo);

        $rawPdo->exec('INSERT INTO settings (id) VALUES (1)');
        $rawPdo->exec('INSERT INTO settings DEFAULT VALUES');
        $ztdPdo->exec('INSERT INTO settings (id) VALUES (1)');
        $ztdPdo->exec('INSERT INTO settings DEFAULT VALUES');

        $raw = $rawPdo->query('SELECT * FROM settings ORDER BY id');
        $ztd = $ztdPdo->query('SELECT * FROM settings ORDER BY id');
        self::assertNotFalse($raw);
        self::assertNotFalse($ztd);
        self::assertSame($raw->fetchAll(), $ztd->fetchAll());
    }

    public function testOmittedIntegerPrimaryKeyUsesShadowIdentity(): void
    {
        $rawPdo = new PDO('sqlite::memory:', null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $rawPdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL)');
        $ztdPdo = ZtdPdo::fromPdo($rawPdo);

        $ztdPdo->exec("INSERT INTO users (name) VALUES ('Alice'), ('Bob')");

        $ztdRows = $ztdPdo->query('SELECT id, name FROM users ORDER BY id');
        $rawRows = $rawPdo->query('SELECT * FROM users');
        self::assertNotFalse($ztdRows);
        self::assertNotFalse($rawRows);
        self::assertSame([['id' => 1, 'name' => 'Alice'], ['id' => 2, 'name' => 'Bob']], $ztdRows->fetchAll());
        self::assertSame([], $rawRows->fetchAll());
    }

    public function testInsertSelectPreservesExpressionsDistinctAndWindows(): void
    {
        $rawPdo = new PDO('sqlite::memory:', null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $rawPdo->exec('CREATE TABLE products (id INTEGER PRIMARY KEY, name TEXT, price REAL, dept TEXT)');
        $rawPdo->exec('CREATE TABLE archive (id INTEGER PRIMARY KEY, name TEXT, doubled REAL, rank_in_dept INTEGER)');
        $rawPdo->exec('CREATE TABLE departments (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT)');
        $rawPdo->exec('CREATE TABLE popular (dept TEXT, total REAL, item_count INTEGER)');
        $rawPdo->exec('CREATE TABLE conditional_users (id INTEGER PRIMARY KEY, name TEXT)');
        $ztdPdo = ZtdPdo::fromPdo($rawPdo);

        $ztdPdo->exec("INSERT INTO products VALUES (1, 'A', 10, 'x'), (2, 'B', 30, 'x'), (3, 'C', 20, 'y')");
        $ztdPdo->exec('INSERT INTO archive SELECT id, name, price * 2, CAST(ROW_NUMBER() OVER (PARTITION BY dept ORDER BY price DESC) AS INTEGER) FROM products');
        $ztdPdo->exec('INSERT INTO departments (name) SELECT DISTINCT dept FROM products ORDER BY dept');
        $popularInsert = $ztdPdo->prepare('INSERT INTO popular SELECT dept, SUM(price), COUNT(*) FROM products GROUP BY dept HAVING SUM(price) > ?');
        self::assertInstanceOf(PDOStatement::class, $popularInsert);
        $popularInsert->bindValue(1, 15, PDO::PARAM_INT);
        $popularInsert->execute();
        $ztdPdo->exec("INSERT INTO conditional_users SELECT 1, 'alice' WHERE NOT EXISTS (SELECT 1 FROM conditional_users WHERE name = 'alice')");
        $ztdPdo->exec("INSERT INTO conditional_users SELECT 1, 'alice' WHERE NOT EXISTS (SELECT 1 FROM conditional_users WHERE name = 'alice')");

        $archive = $ztdPdo->query('SELECT * FROM archive ORDER BY id');
        $departments = $ztdPdo->query('SELECT * FROM departments ORDER BY id');
        $popular = $ztdPdo->query('SELECT * FROM popular ORDER BY dept');
        $conditional = $ztdPdo->query('SELECT * FROM conditional_users');
        self::assertNotFalse($archive);
        self::assertNotFalse($departments);
        self::assertNotFalse($popular);
        self::assertNotFalse($conditional);
        self::assertEquals([
            ['id' => 1, 'name' => 'A', 'doubled' => 20, 'rank_in_dept' => 2],
            ['id' => 2, 'name' => 'B', 'doubled' => 60, 'rank_in_dept' => 1],
            ['id' => 3, 'name' => 'C', 'doubled' => 40, 'rank_in_dept' => 1],
        ], $archive->fetchAll());
        self::assertSame([['id' => 1, 'name' => 'x'], ['id' => 2, 'name' => 'y']], $departments->fetchAll());
        self::assertEquals([['dept' => 'x', 'total' => 40, 'item_count' => 2], ['dept' => 'y', 'total' => 20, 'item_count' => 1]], $popular->fetchAll());
        self::assertSame([['id' => 1, 'name' => 'alice']], $conditional->fetchAll());
    }
}
