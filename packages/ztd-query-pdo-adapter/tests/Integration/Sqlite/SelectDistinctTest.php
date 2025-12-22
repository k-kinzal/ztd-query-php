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
final class SelectDistinctTest extends TestCase
{
    public function testSelectDistinct(): void
    {
        $rawPdo = new \PDO('sqlite::memory:', null, null, [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
        ]);
        $rawPdo->exec("CREATE TABLE items (id INTEGER PRIMARY KEY, category TEXT NOT NULL, name TEXT NOT NULL)");
        $rawPdo->exec("INSERT INTO items (id, category, name) VALUES (1, 'A', 'x'), (2, 'B', 'y'), (3, 'A', 'z'), (4, 'B', 'w')");

        $ztdPdo = ZtdPdo::fromPdo($rawPdo);
        $ztdPdo->exec("INSERT INTO items (id, category, name) VALUES (1, 'A', 'x'), (2, 'B', 'y'), (3, 'A', 'z'), (4, 'B', 'w')");

        $stmt = $rawPdo->query("SELECT DISTINCT category FROM items ORDER BY category");
        self::assertNotFalse($stmt);
        /** @var list<array<string, mixed>> $rawRows */
        $rawRows = $stmt->fetchAll();
        $stmt = $ztdPdo->query("SELECT DISTINCT category FROM items ORDER BY category");
        self::assertNotFalse($stmt);
        /** @var list<array<string, mixed>> $ztdRows */
        $ztdRows = $stmt->fetchAll();

        self::assertSame($rawRows, $ztdRows);
    }
}
