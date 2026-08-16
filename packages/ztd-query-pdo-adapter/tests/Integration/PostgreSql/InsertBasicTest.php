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
final class InsertBasicTest extends TestCase
{
    public function testSingleRowInsert(): void
    {
        [$schemaName, $rawPdo] = PostgreSqlContainer::createTestSchema();
        $table = 'prefix_' . bin2hex(random_bytes(8));

        try {
            $rawPdo->exec("CREATE TABLE {$table} (id INTEGER PRIMARY KEY, name TEXT NOT NULL, age INTEGER NOT NULL)");

            $ztdPdo = ZtdPdo::fromPdo($rawPdo);

            $rawPdo->exec("INSERT INTO {$table} (id, name, age) VALUES (1, 'Alice', 30)");
            $ztdPdo->exec("INSERT INTO {$table} (id, name, age) VALUES (1, 'Alice', 30)");

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

    public function testMultiRowInsert(): void
    {
        [$schemaName, $rawPdo] = PostgreSqlContainer::createTestSchema();
        $table = 'prefix_' . bin2hex(random_bytes(8));

        try {
            $rawPdo->exec("CREATE TABLE {$table} (id INTEGER PRIMARY KEY, name TEXT NOT NULL, age INTEGER NOT NULL)");

            $ztdPdo = ZtdPdo::fromPdo($rawPdo);

            $rawPdo->exec("INSERT INTO {$table} (id, name, age) VALUES (1, 'Alice', 30), (2, 'Bob', 25)");
            $ztdPdo->exec("INSERT INTO {$table} (id, name, age) VALUES (1, 'Alice', 30), (2, 'Bob', 25)");

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

    public function testInsertDoesNotModifyPhysicalDatabase(): void
    {
        [$schemaName, $rawPdo] = PostgreSqlContainer::createTestSchema();
        $table = 'prefix_' . bin2hex(random_bytes(8));

        try {
            $rawPdo->exec("CREATE TABLE {$table} (id INTEGER PRIMARY KEY, name TEXT NOT NULL, age INTEGER NOT NULL)");

            $ztdPdo = ZtdPdo::fromPdo($rawPdo);

            $ztdPdo->exec("INSERT INTO {$table} (id, name, age) VALUES (1, 'Alice', 30)");

            $stmt = $rawPdo->query("SELECT * FROM {$table}");
            self::assertNotFalse($stmt);
            /** @var list<array<string, mixed>> */
            $rawRows = $stmt->fetchAll();

            self::assertCount(0, $rawRows);
        } finally {
            $rawPdo->exec(sprintf('DROP SCHEMA IF EXISTS "%s" CASCADE', $schemaName));
        }
    }

    public function testOmittedExplicitAndDefaultValuesMatchPostgreSql(): void
    {
        [$schemaName, $rawPdo] = PostgreSqlContainer::createTestSchema();
        $table = 'prefix_' . bin2hex(random_bytes(8));

        try {
            $rawPdo->exec("CREATE TABLE {$table} (id INTEGER DEFAULT 7, status TEXT DEFAULT 'active', note TEXT)");
            $ztdPdo = ZtdPdo::fromPdo($rawPdo);

            $rawPdo->exec("INSERT INTO {$table} (id, status) VALUES (1, DEFAULT)");
            $rawPdo->exec("INSERT INTO {$table} DEFAULT VALUES");
            $ztdPdo->exec("INSERT INTO {$table} (id, status) VALUES (1, DEFAULT)");
            $ztdPdo->exec("INSERT INTO {$table} DEFAULT VALUES");

            $raw = $rawPdo->query("SELECT * FROM {$table} ORDER BY id");
            $ztd = $ztdPdo->query("SELECT * FROM {$table} ORDER BY id");
            self::assertNotFalse($raw);
            self::assertNotFalse($ztd);
            self::assertSame($raw->fetchAll(), $ztd->fetchAll());
        } finally {
            $rawPdo->exec(sprintf('DROP SCHEMA IF EXISTS "%s" CASCADE', $schemaName));
        }
    }

    public function testSerialUsesShadowSequenceWithoutAdvancingPhysicalSequence(): void
    {
        [$schemaName, $rawPdo] = PostgreSqlContainer::createTestSchema();
        $table = 'prefix_' . bin2hex(random_bytes(8));

        try {
            $rawPdo->exec("CREATE TABLE {$table} (id SERIAL PRIMARY KEY, name TEXT NOT NULL)");
            $ztdPdo = ZtdPdo::fromPdo($rawPdo);

            $ztdPdo->exec("INSERT INTO {$table} (name) VALUES ('Alice'), ('Bob')");

            $rows = $ztdPdo->query("SELECT id, name FROM {$table} ORDER BY id");
            $sequence = $rawPdo->query("SELECT last_value, is_called FROM {$table}_id_seq");
            self::assertNotFalse($rows);
            self::assertNotFalse($sequence);
            self::assertSame([['id' => 1, 'name' => 'Alice'], ['id' => 2, 'name' => 'Bob']], $rows->fetchAll());
            self::assertSame(['last_value' => 1, 'is_called' => false], $sequence->fetch());
        } finally {
            $rawPdo->exec(sprintf('DROP SCHEMA IF EXISTS "%s" CASCADE', $schemaName));
        }
    }
}
