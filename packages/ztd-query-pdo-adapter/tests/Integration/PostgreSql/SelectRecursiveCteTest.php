<?php

declare(strict_types=1);

namespace Tests\Integration\PostgreSql;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;
use Tests\Fixtures\PostgreSqlContainer;
use ZtdQuery\Adapter\Pdo\ZtdPdo;

/**
 * @requires extension pdo_pgsql
 * @group integration
 * @group postgres
 */
#[CoversNothing]
#[Large]
final class SelectRecursiveCteTest extends TestCase
{
    public function testRecursiveCte(): void
    {
        [$schemaName, $rawPdo] = PostgreSqlContainer::createTestSchema();
        $table = 'prefix_' . bin2hex(random_bytes(8));

        try {
            $rawPdo->exec("CREATE TABLE {$table} (id INTEGER PRIMARY KEY, parent_id INTEGER, name TEXT NOT NULL)");
            $rawPdo->exec("INSERT INTO {$table} (id, parent_id, name) VALUES (1, NULL, 'Root'), (2, 1, 'Child1'), (3, 1, 'Child2'), (4, 2, 'Grandchild1')");

            $ztdPdo = ZtdPdo::fromPdo($rawPdo);

            $ztdPdo->exec("INSERT INTO {$table} (id, parent_id, name) VALUES (1, NULL, 'Root'), (2, 1, 'Child1'), (3, 1, 'Child2'), (4, 2, 'Grandchild1')");

            $sql = "WITH RECURSIVE tree AS ("
                . "SELECT id, parent_id, name, 0 AS depth FROM {$table} WHERE parent_id IS NULL "
                . "UNION ALL "
                . "SELECT c.id, c.parent_id, c.name, t.depth + 1 FROM {$table} c INNER JOIN tree t ON c.parent_id = t.id"
                . ") SELECT * FROM tree ORDER BY id";

            $stmt = $rawPdo->query($sql);
            self::assertNotFalse($stmt);
            /** @var list<array<string, mixed>> */
            $rawRows = $stmt->fetchAll();

            $stmt = $ztdPdo->query($sql);
            self::assertNotFalse($stmt);
            /** @var list<array<string, mixed>> */
            $ztdRows = $stmt->fetchAll();

            self::assertSame($rawRows, $ztdRows);
        } finally {
            $rawPdo->exec(sprintf('DROP SCHEMA IF EXISTS "%s" CASCADE', $schemaName));
        }
    }
}
