<?php

declare(strict_types=1);

namespace Tests\Integration\Sqlite;

use PDO;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Adapter\Pdo\ZtdPdo;

/**
 * @requires extension pdo_sqlite
 */
#[CoversNothing]
#[Large]
final class IndexHintTest extends TestCase
{
    public function testSelectIndexedByReadsShadowRows(): void
    {
        $rawPdo = new PDO('sqlite::memory:', null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $rawPdo->exec('CREATE TABLE products (id INTEGER PRIMARY KEY, name TEXT, category TEXT, price REAL)');
        $rawPdo->exec('CREATE INDEX idx_category ON products (category)');
        $ztdPdo = ZtdPdo::fromPdo($rawPdo);
        $ztdPdo->exec("INSERT INTO products VALUES (1, 'Widget', 'tools', 9.99)");

        $statement = $ztdPdo->query("SELECT name FROM products INDEXED BY idx_category WHERE category = 'tools'");

        self::assertNotFalse($statement);
        self::assertSame([['name' => 'Widget']], $statement->fetchAll());
    }

    public function testPreparedSelectIndexedByReadsShadowRows(): void
    {
        $rawPdo = new PDO('sqlite::memory:', null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $rawPdo->exec('CREATE TABLE products (id INTEGER PRIMARY KEY, name TEXT, category TEXT, price REAL)');
        $rawPdo->exec('CREATE INDEX idx_category ON products (category)');
        $ztdPdo = ZtdPdo::fromPdo($rawPdo);
        $ztdPdo->exec("INSERT INTO products VALUES (1, 'Widget', 'tools', 9.99)");

        $statement = $ztdPdo->prepare('SELECT name FROM products INDEXED BY idx_category WHERE category = ?');
        self::assertNotFalse($statement);
        $statement->execute(['tools']);

        self::assertSame([['name' => 'Widget']], $statement->fetchAll());
    }

    public function testSelectNotIndexedReadsShadowRows(): void
    {
        $rawPdo = new PDO('sqlite::memory:', null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $rawPdo->exec('CREATE TABLE products (id INTEGER PRIMARY KEY, name TEXT, category TEXT, price REAL)');
        $ztdPdo = ZtdPdo::fromPdo($rawPdo);
        $ztdPdo->exec("INSERT INTO products VALUES (1, 'Widget', 'tools', 9.99)");

        $statement = $ztdPdo->query("SELECT name FROM products NOT INDEXED WHERE category = 'tools'");

        self::assertNotFalse($statement);
        self::assertSame([['name' => 'Widget']], $statement->fetchAll());
    }

    public function testUpdateIndexedByMutatesShadowRows(): void
    {
        $rawPdo = new PDO('sqlite::memory:', null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $rawPdo->exec('CREATE TABLE products (id INTEGER PRIMARY KEY, name TEXT, category TEXT, price REAL)');
        $rawPdo->exec('CREATE INDEX idx_category ON products (category)');
        $ztdPdo = ZtdPdo::fromPdo($rawPdo);
        $ztdPdo->exec("INSERT INTO products VALUES (1, 'Widget', 'tools', 9.99)");

        self::assertSame(1, $ztdPdo->exec("UPDATE products INDEXED BY idx_category SET price = 19.99 WHERE category = 'tools'"));
        $statement = $ztdPdo->query('SELECT price FROM products WHERE id = 1');

        self::assertNotFalse($statement);
        self::assertSame([['price' => 19.99]], $statement->fetchAll());
    }

    public function testDeleteIndexedByMutatesShadowRows(): void
    {
        $rawPdo = new PDO('sqlite::memory:', null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $rawPdo->exec('CREATE TABLE products (id INTEGER PRIMARY KEY, name TEXT, category TEXT, price REAL)');
        $rawPdo->exec('CREATE INDEX idx_category ON products (category)');
        $ztdPdo = ZtdPdo::fromPdo($rawPdo);
        $ztdPdo->exec("INSERT INTO products VALUES (1, 'Widget', 'tools', 9.99)");

        self::assertSame(1, $ztdPdo->exec("DELETE FROM products INDEXED BY idx_category WHERE category = 'tools'"));
        $statement = $ztdPdo->query('SELECT * FROM products');

        self::assertNotFalse($statement);
        self::assertSame([], $statement->fetchAll());
    }
}
