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
final class UpdateBasicTest extends TestCase
{
    public function testUpdateReplacesExistingTextWithEmptyString(): void
    {
        [$schemaName, $rawPdo] = PostgreSqlContainer::createTestSchema();
        $table = 'prefix_' . bin2hex(random_bytes(8));

        try {
            $rawPdo->exec("CREATE TABLE {$table} (id INTEGER PRIMARY KEY, name TEXT, notes TEXT)");
            $ztdPdo = ZtdPdo::fromPdo($rawPdo);
            $ztdPdo->exec("INSERT INTO {$table} VALUES (1, 'Alice', 'some notes')");

            self::assertSame(1, $ztdPdo->exec("UPDATE {$table} SET notes = '' WHERE name = 'Alice'"));

            $statement = $ztdPdo->query("SELECT notes FROM {$table} WHERE id = 1");
            self::assertNotFalse($statement);
            self::assertSame('', $statement->fetchColumn());
        } finally {
            $rawPdo->exec(sprintf('DROP SCHEMA IF EXISTS "%s" CASCADE', $schemaName));
        }
    }

    public function testUpdateSingleRow(): void
    {
        [$schemaName, $rawPdo] = PostgreSqlContainer::createTestSchema();
        $table = 'prefix_' . bin2hex(random_bytes(8));

        try {
            $rawPdo->exec("CREATE TABLE {$table} (id INTEGER PRIMARY KEY, name TEXT NOT NULL, age INTEGER NOT NULL)");
            $rawPdo->exec("INSERT INTO {$table} (id, name, age) VALUES (1, 'Alice', 30), (2, 'Bob', 25), (3, 'Charlie', 35)");

            $ztdPdo = ZtdPdo::fromPdo($rawPdo);

            $ztdPdo->exec("INSERT INTO {$table} (id, name, age) VALUES (1, 'Alice', 30), (2, 'Bob', 25), (3, 'Charlie', 35)");

            $rawPdo->exec("UPDATE {$table} SET name = 'Alice Updated' WHERE id = 1");
            $ztdPdo->exec("UPDATE {$table} SET name = 'Alice Updated' WHERE id = 1");

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

    public function testUpdateMultipleColumns(): void
    {
        [$schemaName, $rawPdo] = PostgreSqlContainer::createTestSchema();
        $table = 'prefix_' . bin2hex(random_bytes(8));

        try {
            $rawPdo->exec("CREATE TABLE {$table} (id INTEGER PRIMARY KEY, name TEXT NOT NULL, age INTEGER NOT NULL)");
            $rawPdo->exec("INSERT INTO {$table} (id, name, age) VALUES (1, 'Alice', 30), (2, 'Bob', 25), (3, 'Charlie', 35)");

            $ztdPdo = ZtdPdo::fromPdo($rawPdo);

            $ztdPdo->exec("INSERT INTO {$table} (id, name, age) VALUES (1, 'Alice', 30), (2, 'Bob', 25), (3, 'Charlie', 35)");

            $rawPdo->exec("UPDATE {$table} SET name = 'Alice Updated', age = 31 WHERE id = 1");
            $ztdPdo->exec("UPDATE {$table} SET name = 'Alice Updated', age = 31 WHERE id = 1");

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

    public function testUpdateDoesNotModifyPhysicalDatabase(): void
    {
        [$schemaName, $rawPdo] = PostgreSqlContainer::createTestSchema();
        $table = 'prefix_' . bin2hex(random_bytes(8));

        try {
            $rawPdo->exec("CREATE TABLE {$table} (id INTEGER PRIMARY KEY, name TEXT NOT NULL, age INTEGER NOT NULL)");
            $rawPdo->exec("INSERT INTO {$table} (id, name, age) VALUES (1, 'Alice', 30), (2, 'Bob', 25), (3, 'Charlie', 35)");

            $ztdPdo = ZtdPdo::fromPdo($rawPdo);

            $ztdPdo->exec("INSERT INTO {$table} (id, name, age) VALUES (1, 'Alice', 30), (2, 'Bob', 25), (3, 'Charlie', 35)");

            $ztdPdo->exec("UPDATE {$table} SET name = 'Modified' WHERE id = 1");

            $stmt = $rawPdo->query("SELECT name FROM {$table} WHERE id = 1");
            self::assertNotFalse($stmt);
            /** @var list<array<string, mixed>> */
            $rawRows = $stmt->fetchAll();

            self::assertSame('Alice', $rawRows[0]['name']);
        } finally {
            $rawPdo->exec(sprintf('DROP SCHEMA IF EXISTS "%s" CASCADE', $schemaName));
        }
    }

    public function testUpdateSetPreservesFromKeywordsInsideFunctions(): void
    {
        [$schemaName, $rawPdo] = PostgreSqlContainer::createTestSchema();
        $table = 'prefix_' . bin2hex(random_bytes(8));

        try {
            $rawPdo->exec("CREATE TABLE {$table} (id INTEGER PRIMARY KEY, name TEXT NOT NULL, code TEXT NOT NULL)");
            $rawPdo->exec("INSERT INTO {$table} (id, name, code) VALUES (1, '  Alice  ', 'abcdef')");

            $ztdPdo = ZtdPdo::fromPdo($rawPdo);
            $ztdPdo->exec("INSERT INTO {$table} (id, name, code) VALUES (1, '  Alice  ', 'abcdef')");

            $sql = "UPDATE {$table} SET name = TRIM(BOTH ' ' FROM name), code = SUBSTRING(code FROM 2 FOR 3) WHERE id = 1";
            $rawPdo->exec($sql);
            $ztdPdo->exec($sql);

            $rawStatement = $rawPdo->query("SELECT * FROM {$table}");
            $ztdStatement = $ztdPdo->query("SELECT * FROM {$table}");
            self::assertNotFalse($rawStatement);
            self::assertNotFalse($ztdStatement);
            self::assertSame($rawStatement->fetchAll(), $ztdStatement->fetchAll());
        } finally {
            $rawPdo->exec(sprintf('DROP SCHEMA IF EXISTS "%s" CASCADE', $schemaName));
        }
    }
}
