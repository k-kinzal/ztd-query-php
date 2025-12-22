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
final class SelectGroupByTest extends TestCase
{
    public function testGroupByWithCount(): void
    {
        $rawPdo = new \PDO('sqlite::memory:', null, null, [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
        ]);
        $rawPdo->exec("CREATE TABLE sales (id INTEGER PRIMARY KEY, category TEXT NOT NULL, amount INTEGER NOT NULL)");
        $rawPdo->exec("INSERT INTO sales (id, category, amount) VALUES (1, 'A', 100), (2, 'B', 200), (3, 'A', 150), (4, 'B', 50), (5, 'C', 300)");

        $ztdPdo = ZtdPdo::fromPdo($rawPdo);
        $ztdPdo->exec("INSERT INTO sales (id, category, amount) VALUES (1, 'A', 100), (2, 'B', 200), (3, 'A', 150), (4, 'B', 50), (5, 'C', 300)");

        $sql = "SELECT category, COUNT(*) AS cnt FROM sales GROUP BY category ORDER BY category";

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

    public function testGroupByWithSum(): void
    {
        $rawPdo = new \PDO('sqlite::memory:', null, null, [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
        ]);
        $rawPdo->exec("CREATE TABLE sales (id INTEGER PRIMARY KEY, category TEXT NOT NULL, amount INTEGER NOT NULL)");
        $rawPdo->exec("INSERT INTO sales (id, category, amount) VALUES (1, 'A', 100), (2, 'B', 200), (3, 'A', 150), (4, 'B', 50), (5, 'C', 300)");

        $ztdPdo = ZtdPdo::fromPdo($rawPdo);
        $ztdPdo->exec("INSERT INTO sales (id, category, amount) VALUES (1, 'A', 100), (2, 'B', 200), (3, 'A', 150), (4, 'B', 50), (5, 'C', 300)");

        $sql = "SELECT category, SUM(amount) AS total FROM sales GROUP BY category ORDER BY category";

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

    public function testGroupByWithMinMax(): void
    {
        $rawPdo = new \PDO('sqlite::memory:', null, null, [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
        ]);
        $rawPdo->exec("CREATE TABLE sales (id INTEGER PRIMARY KEY, category TEXT NOT NULL, amount INTEGER NOT NULL)");
        $rawPdo->exec("INSERT INTO sales (id, category, amount) VALUES (1, 'A', 100), (2, 'B', 200), (3, 'A', 150), (4, 'B', 50), (5, 'C', 300)");

        $ztdPdo = ZtdPdo::fromPdo($rawPdo);
        $ztdPdo->exec("INSERT INTO sales (id, category, amount) VALUES (1, 'A', 100), (2, 'B', 200), (3, 'A', 150), (4, 'B', 50), (5, 'C', 300)");

        $sql = "SELECT category, MIN(amount) AS min_amt, MAX(amount) AS max_amt FROM sales GROUP BY category ORDER BY category";

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
