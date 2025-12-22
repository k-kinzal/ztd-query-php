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
final class SelectWindowTest extends TestCase
{
    public function testRowNumber(): void
    {
        $rawPdo = new \PDO('sqlite::memory:', null, null, [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION, \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC]);
        $rawPdo->exec("CREATE TABLE sales (id INTEGER PRIMARY KEY, category TEXT NOT NULL, amount INTEGER NOT NULL)");
        $rawPdo->exec("INSERT INTO sales (id, category, amount) VALUES (1, 'A', 100), (2, 'B', 200), (3, 'A', 150), (4, 'B', 50), (5, 'C', 300)");
        $ztdPdo = ZtdPdo::fromPdo($rawPdo);
        $ztdPdo->exec("INSERT INTO sales (id, category, amount) VALUES (1, 'A', 100), (2, 'B', 200), (3, 'A', 150), (4, 'B', 50), (5, 'C', 300)");

        $sql = "SELECT id, category, amount, ROW_NUMBER() OVER (ORDER BY id) AS rn FROM sales ORDER BY id";
        $stmt = $rawPdo->query($sql);
        self::assertNotFalse($stmt);
        /** @var list<array<string, mixed>> $rawRows */
        $rawRows = $stmt->fetchAll();
        $stmt = $ztdPdo->query($sql);
        self::assertNotFalse($stmt);
        /** @var list<array<string, mixed>> $ztdRows */
        $ztdRows = $stmt->fetchAll();
        self::assertSame($rawRows, $ztdRows);
    }

    public function testRankPartitionBy(): void
    {
        $rawPdo = new \PDO('sqlite::memory:', null, null, [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION, \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC]);
        $rawPdo->exec("CREATE TABLE sales (id INTEGER PRIMARY KEY, category TEXT NOT NULL, amount INTEGER NOT NULL)");
        $rawPdo->exec("INSERT INTO sales (id, category, amount) VALUES (1, 'A', 100), (2, 'B', 200), (3, 'A', 150), (4, 'B', 50), (5, 'C', 300)");
        $ztdPdo = ZtdPdo::fromPdo($rawPdo);
        $ztdPdo->exec("INSERT INTO sales (id, category, amount) VALUES (1, 'A', 100), (2, 'B', 200), (3, 'A', 150), (4, 'B', 50), (5, 'C', 300)");

        $sql = "SELECT id, category, amount, RANK() OVER (PARTITION BY category ORDER BY amount DESC) AS rnk FROM sales ORDER BY id";
        $stmt = $rawPdo->query($sql);
        self::assertNotFalse($stmt);
        /** @var list<array<string, mixed>> $rawRows */
        $rawRows = $stmt->fetchAll();
        $stmt = $ztdPdo->query($sql);
        self::assertNotFalse($stmt);
        /** @var list<array<string, mixed>> $ztdRows */
        $ztdRows = $stmt->fetchAll();
        self::assertSame($rawRows, $ztdRows);
    }

    public function testSumOver(): void
    {
        $rawPdo = new \PDO('sqlite::memory:', null, null, [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION, \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC]);
        $rawPdo->exec("CREATE TABLE sales (id INTEGER PRIMARY KEY, category TEXT NOT NULL, amount INTEGER NOT NULL)");
        $rawPdo->exec("INSERT INTO sales (id, category, amount) VALUES (1, 'A', 100), (2, 'B', 200), (3, 'A', 150), (4, 'B', 50), (5, 'C', 300)");
        $ztdPdo = ZtdPdo::fromPdo($rawPdo);
        $ztdPdo->exec("INSERT INTO sales (id, category, amount) VALUES (1, 'A', 100), (2, 'B', 200), (3, 'A', 150), (4, 'B', 50), (5, 'C', 300)");

        $sql = "SELECT id, category, amount, SUM(amount) OVER (PARTITION BY category) AS cat_total FROM sales ORDER BY id";
        $stmt = $rawPdo->query($sql);
        self::assertNotFalse($stmt);
        /** @var list<array<string, mixed>> $rawRows */
        $rawRows = $stmt->fetchAll();
        $stmt = $ztdPdo->query($sql);
        self::assertNotFalse($stmt);
        /** @var list<array<string, mixed>> $ztdRows */
        $ztdRows = $stmt->fetchAll();
        self::assertSame($rawRows, $ztdRows);
    }
}
