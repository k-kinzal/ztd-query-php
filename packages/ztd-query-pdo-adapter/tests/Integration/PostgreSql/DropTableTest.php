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
final class DropTableTest extends TestCase
{
    public function testDropTableRemovesFromRegistry(): void
    {
        [$schemaName, $rawPdo] = PostgreSqlContainer::createTestSchema();
        $table = 'prefix_' . bin2hex(random_bytes(8));

        try {
            $rawPdo->exec("CREATE TABLE {$table} (id INTEGER PRIMARY KEY, name TEXT NOT NULL)");
            $rawPdo->exec("INSERT INTO {$table} (id, name) VALUES (1, 'Alice')");

            $ztdPdo = ZtdPdo::fromPdo($rawPdo);

            $ztdPdo->exec("INSERT INTO {$table} (id, name) VALUES (1, 'Alice')");

            $stmt = $ztdPdo->query("SELECT * FROM {$table}");
            self::assertNotFalse($stmt);
            /** @var list<array<string, mixed>> */
            $rows = $stmt->fetchAll();

            self::assertCount(1, $rows);

            $ztdPdo->exec("DROP TABLE {$table}");

            $stmt = $rawPdo->query("SELECT * FROM {$table}");
            self::assertNotFalse($stmt);
            /** @var list<array<string, mixed>> */
            $rawRows = $stmt->fetchAll();

            self::assertCount(1, $rawRows);
        } finally {
            $rawPdo->exec(sprintf('DROP SCHEMA IF EXISTS "%s" CASCADE', $schemaName));
        }
    }

    public function testDropTableIfExists(): void
    {
        [$schemaName, $rawPdo] = PostgreSqlContainer::createTestSchema();
        $table = 'prefix_' . bin2hex(random_bytes(8));

        try {
            $ztdPdo = ZtdPdo::fromPdo($rawPdo, null);

            $ztdPdo->exec("DROP TABLE IF EXISTS {$table}");
            self::addToAssertionCount(1);
        } finally {
            $rawPdo->exec(sprintf('DROP SCHEMA IF EXISTS "%s" CASCADE', $schemaName));
        }
    }
}
