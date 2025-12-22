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
final class CreateTableTest extends TestCase
{
    public function testCreateTableAndInsert(): void
    {
        [$schemaName, $rawPdo] = PostgreSqlContainer::createTestSchema();
        $table = 'prefix_' . bin2hex(random_bytes(8));

        try {
            $ztdPdo = ZtdPdo::fromPdo($rawPdo, null);

            $ztdPdo->exec("CREATE TABLE {$table} (id INTEGER PRIMARY KEY, name TEXT NOT NULL)");

            $ztdPdo->exec("INSERT INTO {$table} (id, name) VALUES (1, 'Alice')");

            $stmt = $ztdPdo->query("SELECT * FROM {$table} ORDER BY id");
            self::assertNotFalse($stmt);
            /** @var list<array<string, mixed>> */
            $ztdRows = $stmt->fetchAll();

            self::assertCount(1, $ztdRows);
            self::assertSame(1, $ztdRows[0]['id']);
            self::assertSame('Alice', $ztdRows[0]['name']);
        } finally {
            $rawPdo->exec(sprintf('DROP SCHEMA IF EXISTS "%s" CASCADE', $schemaName));
        }
    }

    public function testCreateTableIfNotExists(): void
    {
        [$schemaName, $rawPdo] = PostgreSqlContainer::createTestSchema();
        $table = 'prefix_' . bin2hex(random_bytes(8));

        try {
            $ztdPdo = ZtdPdo::fromPdo($rawPdo, null);

            $ztdPdo->exec("CREATE TABLE {$table} (id INTEGER PRIMARY KEY, name TEXT NOT NULL)");

            $ztdPdo->exec("CREATE TABLE IF NOT EXISTS {$table} (id INTEGER PRIMARY KEY, name TEXT NOT NULL)");

            $ztdPdo->exec("INSERT INTO {$table} (id, name) VALUES (1, 'Test')");

            $stmt = $ztdPdo->query("SELECT * FROM {$table}");
            self::assertNotFalse($stmt);
            /** @var list<array<string, mixed>> */
            $ztdRows = $stmt->fetchAll();

            self::assertCount(1, $ztdRows);
        } finally {
            $rawPdo->exec(sprintf('DROP SCHEMA IF EXISTS "%s" CASCADE', $schemaName));
        }
    }

    public function testCreateTableDoesNotModifyPhysicalDatabase(): void
    {
        [$schemaName, $rawPdo] = PostgreSqlContainer::createTestSchema();
        $table = 'prefix_' . bin2hex(random_bytes(8));

        try {
            $ztdPdo = ZtdPdo::fromPdo($rawPdo, null);

            $ztdPdo->exec("CREATE TABLE {$table} (id INTEGER PRIMARY KEY, name TEXT NOT NULL)");

            $stmt = $rawPdo->prepare(
                "SELECT table_name FROM information_schema.tables WHERE table_name = ? AND table_schema = current_schema()"
            );
            $stmt->execute([$table]);
            $rows = $stmt->fetchAll();
            self::assertCount(0, $rows);
        } finally {
            $rawPdo->exec(sprintf('DROP SCHEMA IF EXISTS "%s" CASCADE', $schemaName));
        }
    }
}
