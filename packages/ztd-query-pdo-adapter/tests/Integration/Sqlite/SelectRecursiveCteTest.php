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
final class SelectRecursiveCteTest extends TestCase
{
    public function testRecursiveCte(): void
    {
        $rawPdo = new \PDO('sqlite::memory:', null, null, [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
        ]);
        $rawPdo->exec("CREATE TABLE categories (id INTEGER PRIMARY KEY, parent_id INTEGER, name TEXT NOT NULL)");
        $rawPdo->exec("INSERT INTO categories (id, parent_id, name) VALUES (1, NULL, 'Root'), (2, 1, 'Child1'), (3, 1, 'Child2'), (4, 2, 'Grandchild1')");

        $ztdPdo = ZtdPdo::fromPdo($rawPdo);
        $ztdPdo->exec("INSERT INTO categories (id, parent_id, name) VALUES (1, NULL, 'Root'), (2, 1, 'Child1'), (3, 1, 'Child2'), (4, 2, 'Grandchild1')");

        $sql = "WITH RECURSIVE tree AS ("
            . "SELECT id, parent_id, name, 0 AS depth FROM categories WHERE parent_id IS NULL "
            . "UNION ALL "
            . "SELECT c.id, c.parent_id, c.name, t.depth + 1 FROM categories c INNER JOIN tree t ON c.parent_id = t.id"
            . ") SELECT * FROM tree ORDER BY id";

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
