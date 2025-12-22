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
final class SelectHavingTest extends TestCase
{
    public function testHavingWithCount(): void
    {
        [$schemaName, $rawPdo] = PostgreSqlContainer::createTestSchema();
        $table = 'prefix_' . bin2hex(random_bytes(8));

        try {
            $rawPdo->exec("CREATE TABLE {$table} (id INTEGER PRIMARY KEY, category TEXT NOT NULL, amount INTEGER NOT NULL)");
            $rawPdo->exec("INSERT INTO {$table} (id, category, amount) VALUES (1, 'A', 100), (2, 'B', 200), (3, 'A', 150), (4, 'B', 50), (5, 'C', 300)");

            $ztdPdo = ZtdPdo::fromPdo($rawPdo);

            $ztdPdo->exec("INSERT INTO {$table} (id, category, amount) VALUES (1, 'A', 100), (2, 'B', 200), (3, 'A', 150), (4, 'B', 50), (5, 'C', 300)");

            $sql = "SELECT category, COUNT(*) AS cnt FROM {$table} GROUP BY category HAVING COUNT(*) > 1 ORDER BY category";

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

    public function testHavingWithSum(): void
    {
        [$schemaName, $rawPdo] = PostgreSqlContainer::createTestSchema();
        $table = 'prefix_' . bin2hex(random_bytes(8));

        try {
            $rawPdo->exec("CREATE TABLE {$table} (id INTEGER PRIMARY KEY, category TEXT NOT NULL, amount INTEGER NOT NULL)");
            $rawPdo->exec("INSERT INTO {$table} (id, category, amount) VALUES (1, 'A', 100), (2, 'B', 200), (3, 'A', 150), (4, 'B', 50), (5, 'C', 300)");

            $ztdPdo = ZtdPdo::fromPdo($rawPdo);

            $ztdPdo->exec("INSERT INTO {$table} (id, category, amount) VALUES (1, 'A', 100), (2, 'B', 200), (3, 'A', 150), (4, 'B', 50), (5, 'C', 300)");

            $sql = "SELECT category, SUM(amount) AS total FROM {$table} GROUP BY category HAVING SUM(amount) > 200 ORDER BY category";

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
