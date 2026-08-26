<?php

declare(strict_types=1);

namespace Tests\Integration\Sqlite;

use PDO;
use PDOStatement;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Adapter\Pdo\ZtdPdo;

#[CoversNothing]
#[Large]
final class PreparedExecutionTest extends TestCase
{
    public function testPreparedInsertCanBeUpdatedAndObservedByRepreparedSelect(): void
    {
        $raw = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $raw->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT)');
        $ztdPdo = ZtdPdo::fromPdo($raw);

        $statement = $ztdPdo->prepare('INSERT INTO users VALUES (?, ?)');
        self::assertInstanceOf(PDOStatement::class, $statement);
        self::assertTrue($statement->execute([1, 'Alice']));

        $statement = $ztdPdo->prepare('UPDATE users SET name = ? WHERE id = ?');
        self::assertInstanceOf(PDOStatement::class, $statement);
        self::assertTrue($statement->execute(['Alicia', 1]));

        $statement = $ztdPdo->prepare('SELECT name FROM users WHERE id = 1');
        self::assertInstanceOf(PDOStatement::class, $statement);
        self::assertTrue($statement->execute([]));
        self::assertSame('Alicia', $statement->fetchColumn());
    }

    public function testPreparedExpressionsAndReexecutionUseCurrentTypedShadowRows(): void
    {
        $raw = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $raw->exec('CREATE TABLE products (id INTEGER PRIMARY KEY, name TEXT, price REAL, stock INTEGER)');
        $ztdPdo = ZtdPdo::fromPdo($raw);
        $ztdPdo->exec("INSERT INTO products VALUES (1, 'Widget', 10, 100), (2, 'Gadget', 20, 50), (3, 'Sprocket', NULL, 5)");

        $select = $ztdPdo->prepare('SELECT name FROM products WHERE COALESCE(price, ?) < ? ORDER BY id');
        self::assertInstanceOf(PDOStatement::class, $select);
        self::assertTrue($select->execute([0.0, 15.0]));
        self::assertSame(['Widget', 'Sprocket'], $select->fetchAll(PDO::FETCH_COLUMN));

        $delete = $ztdPdo->prepare('DELETE FROM products WHERE MIN(COALESCE(price, ?), stock) < ?');
        self::assertInstanceOf(PDOStatement::class, $delete);
        self::assertTrue($delete->execute([0.0, 10]));
        self::assertSame(1, $delete->rowCount());

        self::assertTrue($select->execute([0.0, 25.0]));
        self::assertSame(['Widget', 'Gadget'], $select->fetchAll(PDO::FETCH_COLUMN));
    }
}
