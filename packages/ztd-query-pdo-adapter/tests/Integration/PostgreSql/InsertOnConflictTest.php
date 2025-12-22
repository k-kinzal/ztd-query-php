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
final class InsertOnConflictTest extends TestCase
{
    public function testOnConflictDoNothing(): void
    {
        [$schemaName, $rawPdo] = PostgreSqlContainer::createTestSchema();
        $table = 'prefix_' . bin2hex(random_bytes(8));

        try {
            $rawPdo->exec("CREATE TABLE {$table} (id INTEGER PRIMARY KEY, name TEXT NOT NULL, age INTEGER NOT NULL)");

            $ztdPdo = ZtdPdo::fromPdo($rawPdo);

            $rawPdo->exec("INSERT INTO {$table} (id, name, age) VALUES (1, 'Alice', 30)");
            $ztdPdo->exec("INSERT INTO {$table} (id, name, age) VALUES (1, 'Alice', 30)");

            $rawPdo->exec("INSERT INTO {$table} (id, name, age) VALUES (1, 'Duplicate', 99) ON CONFLICT (id) DO NOTHING");
            $ztdPdo->exec("INSERT INTO {$table} (id, name, age) VALUES (1, 'Duplicate', 99) ON CONFLICT (id) DO NOTHING");

            $stmt = $rawPdo->query("SELECT * FROM {$table} ORDER BY id");
            self::assertNotFalse($stmt);
            /** @var list<array<string, mixed>> */
            $rawRows = $stmt->fetchAll();

            $stmt = $ztdPdo->query("SELECT * FROM {$table} ORDER BY id");
            self::assertNotFalse($stmt);
            /** @var list<array<string, mixed>> */
            $ztdRows = $stmt->fetchAll();

            self::assertSame($rawRows, $ztdRows);
        } finally {
            $rawPdo->exec(sprintf('DROP SCHEMA IF EXISTS "%s" CASCADE', $schemaName));
        }
    }

    public function testOnConflictDoUpdate(): void
    {
        [$schemaName, $rawPdo] = PostgreSqlContainer::createTestSchema();
        $table = 'prefix_' . bin2hex(random_bytes(8));

        try {
            $rawPdo->exec("CREATE TABLE {$table} (id INTEGER PRIMARY KEY, name TEXT NOT NULL, age INTEGER NOT NULL)");

            $ztdPdo = ZtdPdo::fromPdo($rawPdo);

            $rawPdo->exec("INSERT INTO {$table} (id, name, age) VALUES (1, 'Alice', 30)");
            $ztdPdo->exec("INSERT INTO {$table} (id, name, age) VALUES (1, 'Alice', 30)");

            $rawPdo->exec("INSERT INTO {$table} (id, name, age) VALUES (1, 'Alice Updated', 31) ON CONFLICT (id) DO UPDATE SET name = EXCLUDED.name, age = EXCLUDED.age");
            $ztdPdo->exec("INSERT INTO {$table} (id, name, age) VALUES (1, 'Alice Updated', 31) ON CONFLICT (id) DO UPDATE SET name = EXCLUDED.name, age = EXCLUDED.age");

            $stmt = $rawPdo->query("SELECT * FROM {$table} ORDER BY id");
            self::assertNotFalse($stmt);
            /** @var list<array<string, mixed>> */
            $rawRows = $stmt->fetchAll();

            $stmt = $ztdPdo->query("SELECT * FROM {$table} ORDER BY id");
            self::assertNotFalse($stmt);
            /** @var list<array<string, mixed>> */
            $ztdRows = $stmt->fetchAll();

            self::assertSame($rawRows, $ztdRows);
        } finally {
            $rawPdo->exec(sprintf('DROP SCHEMA IF EXISTS "%s" CASCADE', $schemaName));
        }
    }
}
