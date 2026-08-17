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
    public function testPreparedOnConflictDoUpdateReplacesExistingRow(): void
    {
        [$schemaName, $rawPdo] = PostgreSqlContainer::createTestSchema();
        $table = 'prefix_' . bin2hex(random_bytes(8));

        try {
            $rawPdo->exec("CREATE TABLE {$table} (id INTEGER PRIMARY KEY, name TEXT NOT NULL)");
            $ztdPdo = ZtdPdo::fromPdo($rawPdo);
            $ztdPdo->exec("INSERT INTO {$table} VALUES (1, 'original')");
            $statement = $ztdPdo->prepare(
                "INSERT INTO {$table} VALUES (?, ?) ON CONFLICT (id) DO UPDATE SET name = EXCLUDED.name"
            );
            self::assertNotFalse($statement);

            self::assertTrue($statement->execute(['1', 'updated']));

            $rows = $ztdPdo->query("SELECT * FROM {$table} WHERE id = 1");
            self::assertNotFalse($rows);
            self::assertSame([['id' => 1, 'name' => 'updated']], $rows->fetchAll());
        } finally {
            $rawPdo->exec(sprintf('DROP SCHEMA IF EXISTS "%s" CASCADE', $schemaName));
        }
    }

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

    public function testConditionalOnConflictAndReturningMatchPostgres(): void
    {
        [$schemaName, $rawPdo] = PostgreSqlContainer::createTestSchema();
        $nativeTable = 'prefix_' . bin2hex(random_bytes(8));
        $shadowTable = 'prefix_' . bin2hex(random_bytes(8));

        try {
            $rawPdo->exec("CREATE TABLE {$nativeTable} (id INTEGER PRIMARY KEY, name TEXT, score INTEGER)");
            $rawPdo->exec("CREATE TABLE {$shadowTable} (id INTEGER PRIMARY KEY, name TEXT, score INTEGER)");
            $ztdPdo = ZtdPdo::fromPdo($rawPdo);
            $rawPdo->exec("INSERT INTO {$nativeTable} VALUES (1, 'original', 50)");
            $ztdPdo->exec("INSERT INTO {$shadowTable} VALUES (1, 'original', 50)");

            $rawSkipped = $rawPdo->query("INSERT INTO {$nativeTable} VALUES (1, 'skipped', 95) ON CONFLICT(id) DO UPDATE SET name = EXCLUDED.name WHERE {$nativeTable}.score >= 80 RETURNING id, name, score");
            $ztdSkipped = $ztdPdo->query("INSERT INTO {$shadowTable} VALUES (1, 'skipped', 95) ON CONFLICT(id) DO UPDATE SET name = EXCLUDED.name WHERE {$shadowTable}.score >= 80 RETURNING id, name, score");
            self::assertNotFalse($rawSkipped);
            self::assertNotFalse($ztdSkipped);
            self::assertSame($rawSkipped->fetchAll(), $ztdSkipped->fetchAll());

            $rawPdo->exec("UPDATE {$nativeTable} SET score = 85 WHERE id = 1");
            $ztdPdo->exec("UPDATE {$shadowTable} SET score = 85 WHERE id = 1");
            $rawUpdated = $rawPdo->query("INSERT INTO {$nativeTable} VALUES (1, 'updated', 95) ON CONFLICT(id) DO UPDATE SET name = EXCLUDED.name WHERE {$nativeTable}.score >= 80 RETURNING id, name, score");
            $ztdUpdated = $ztdPdo->query("INSERT INTO {$shadowTable} VALUES (1, 'updated', 95) ON CONFLICT(id) DO UPDATE SET name = EXCLUDED.name WHERE {$shadowTable}.score >= 80 RETURNING id, name, score");
            self::assertNotFalse($rawUpdated);
            self::assertNotFalse($ztdUpdated);
            self::assertSame($rawUpdated->fetchAll(), $ztdUpdated->fetchAll());
        } finally {
            $rawPdo->exec(sprintf('DROP SCHEMA IF EXISTS "%s" CASCADE', $schemaName));
        }
    }
}
