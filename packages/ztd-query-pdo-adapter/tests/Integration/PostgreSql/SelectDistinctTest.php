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
final class SelectDistinctTest extends TestCase
{
    public function testSelectDistinct(): void
    {
        [$schemaName, $rawPdo] = PostgreSqlContainer::createTestSchema();
        $table = 'prefix_' . bin2hex(random_bytes(8));

        try {
            $rawPdo->exec("CREATE TABLE {$table} (id INTEGER PRIMARY KEY, category TEXT NOT NULL, name TEXT NOT NULL)");
            $rawPdo->exec("INSERT INTO {$table} (id, category, name) VALUES (1, 'A', 'x'), (2, 'B', 'y'), (3, 'A', 'z'), (4, 'B', 'w')");

            $ztdPdo = ZtdPdo::fromPdo($rawPdo);

            $ztdPdo->exec("INSERT INTO {$table} (id, category, name) VALUES (1, 'A', 'x'), (2, 'B', 'y'), (3, 'A', 'z'), (4, 'B', 'w')");

            $stmt = $rawPdo->query("SELECT DISTINCT category FROM {$table} ORDER BY category");
            self::assertNotFalse($stmt);
            /** @var list<array<string, mixed>> */
            $rawRows = $stmt->fetchAll();

            $stmt = $ztdPdo->query("SELECT DISTINCT category FROM {$table} ORDER BY category");
            self::assertNotFalse($stmt);
            /** @var list<array<string, mixed>> */
            $ztdRows = $stmt->fetchAll();

            self::assertSame($rawRows, $ztdRows);
        } finally {
            $rawPdo->exec(sprintf('DROP SCHEMA IF EXISTS "%s" CASCADE', $schemaName));
        }
    }
}
