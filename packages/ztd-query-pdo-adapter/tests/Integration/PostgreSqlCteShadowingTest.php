<?php

declare(strict_types=1);

namespace Tests\Integration;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;
use Tests\Fixtures\PostgreSqlContainer;
use ZtdQuery\Adapter\Pdo\ZtdPdo;

/**
 * Integration tests for ZtdPdo with PostgreSQL: CTE shadowing behavior.
 *
 * PostgreSQL CTE shadowing replaces table references with CTE definitions
 * containing shadow data. Tables with no shadow data are shadowed with
 * empty CTEs (consistent with MySQL behavior).
 *
 * @requires extension pdo_pgsql
 * @group integration
 * @group postgres
 */
#[CoversNothing]
#[Large]
final class PostgreSqlCteShadowingTest extends TestCase
{
    public function testSelectOnCleanShadowReturnsEmpty(): void
    {
        [$schemaName, $rawPdo] = PostgreSqlContainer::createTestSchema();
        $table = 'prefix_' . bin2hex(random_bytes(8));

        try {
            $rawPdo->exec(sprintf(
                'CREATE TABLE %s (id SERIAL PRIMARY KEY, name TEXT NOT NULL, age INTEGER NOT NULL)',
                $table
            ));
            $rawPdo->exec(sprintf(
                "INSERT INTO %s (name, age) VALUES ('Alice', 30), ('Bob', 25)",
                $table
            ));

            $ztdPdo = ZtdPdo::fromPdo($rawPdo, null);

            $stmt = $ztdPdo->query(sprintf('SELECT * FROM %s ORDER BY id', $table));
            self::assertNotFalse($stmt);
            /** @var list<array<string, mixed>> */
            $rows = $stmt->fetchAll();

            self::assertCount(0, $rows);
        } finally {
            $rawPdo->exec(sprintf('DROP SCHEMA IF EXISTS "%s" CASCADE', $schemaName));
        }
    }

    public function testInsertDoesNotModifyPhysicalDatabase(): void
    {
        [$schemaName, $rawPdo] = PostgreSqlContainer::createTestSchema();
        $table = 'prefix_' . bin2hex(random_bytes(8));

        try {
            $rawPdo->exec(sprintf(
                'CREATE TABLE %s (id SERIAL PRIMARY KEY, name TEXT NOT NULL, age INTEGER NOT NULL)',
                $table
            ));
            $rawPdo->exec(sprintf(
                "INSERT INTO %s (name, age) VALUES ('Alice', 30), ('Bob', 25)",
                $table
            ));

            $ztdPdo = ZtdPdo::fromPdo($rawPdo, null);

            $ztdPdo->exec(sprintf(
                "INSERT INTO %s (name, age) VALUES ('Charlie', 35)",
                $table
            ));

            $stmt = $rawPdo->query(sprintf('SELECT * FROM %s', $table));
            self::assertNotFalse($stmt);
            /** @var list<array<string, mixed>> */
            $rawRows = $stmt->fetchAll();

            self::assertCount(2, $rawRows);
        } finally {
            $rawPdo->exec(sprintf('DROP SCHEMA IF EXISTS "%s" CASCADE', $schemaName));
        }
    }

    public function testInsertIsVisibleViaZtdSelect(): void
    {
        [$schemaName, $rawPdo] = PostgreSqlContainer::createTestSchema();
        $table = 'prefix_' . bin2hex(random_bytes(8));

        try {
            $rawPdo->exec(sprintf(
                'CREATE TABLE %s (id SERIAL PRIMARY KEY, name TEXT NOT NULL, age INTEGER NOT NULL)',
                $table
            ));
            $rawPdo->exec(sprintf(
                "INSERT INTO %s (name, age) VALUES ('Alice', 30), ('Bob', 25)",
                $table
            ));

            $ztdPdo = ZtdPdo::fromPdo($rawPdo, null);

            $ztdPdo->exec(sprintf(
                "INSERT INTO %s (name, age) VALUES ('Charlie', 35)",
                $table
            ));

            $stmt = $ztdPdo->query(sprintf('SELECT name, age FROM %s ORDER BY name', $table));
            self::assertNotFalse($stmt);
            /** @var list<array<string, mixed>> */
            $ztdRows = $stmt->fetchAll();

            self::assertCount(1, $ztdRows);
            self::assertSame('Charlie', $ztdRows[0]['name']);
        } finally {
            $rawPdo->exec(sprintf('DROP SCHEMA IF EXISTS "%s" CASCADE', $schemaName));
        }
    }

    public function testMultipleInsertsAccumulate(): void
    {
        [$schemaName, $rawPdo] = PostgreSqlContainer::createTestSchema();
        $table = 'prefix_' . bin2hex(random_bytes(8));

        try {
            $rawPdo->exec(sprintf(
                'CREATE TABLE %s (id SERIAL PRIMARY KEY, name TEXT NOT NULL, age INTEGER NOT NULL)',
                $table
            ));
            $rawPdo->exec(sprintf(
                "INSERT INTO %s (name, age) VALUES ('Alice', 30), ('Bob', 25)",
                $table
            ));

            $ztdPdo = ZtdPdo::fromPdo($rawPdo, null);

            $ztdPdo->exec(sprintf(
                "INSERT INTO %s (name, age) VALUES ('Charlie', 35)",
                $table
            ));
            $ztdPdo->exec(sprintf(
                "INSERT INTO %s (name, age) VALUES ('Diana', 28)",
                $table
            ));

            $stmt = $ztdPdo->query(sprintf('SELECT name FROM %s ORDER BY name', $table));
            self::assertNotFalse($stmt);
            /** @var list<array<string, mixed>> */
            $ztdRows = $stmt->fetchAll();

            self::assertCount(2, $ztdRows);
            $names = array_column($ztdRows, 'name');
            self::assertContains('Charlie', $names);
            self::assertContains('Diana', $names);
        } finally {
            $rawPdo->exec(sprintf('DROP SCHEMA IF EXISTS "%s" CASCADE', $schemaName));
        }
    }

    public function testPhysicalDatabaseRemainsUnchangedAfterMutations(): void
    {
        [$schemaName, $rawPdo] = PostgreSqlContainer::createTestSchema();
        $table = 'prefix_' . bin2hex(random_bytes(8));

        try {
            $rawPdo->exec(sprintf(
                'CREATE TABLE %s (id SERIAL PRIMARY KEY, name TEXT NOT NULL, age INTEGER NOT NULL)',
                $table
            ));
            $rawPdo->exec(sprintf(
                "INSERT INTO %s (name, age) VALUES ('Alice', 30), ('Bob', 25)",
                $table
            ));

            $ztdPdo = ZtdPdo::fromPdo($rawPdo, null);

            $ztdPdo->exec(sprintf(
                "INSERT INTO %s (name, age) VALUES ('Charlie', 35)",
                $table
            ));
            $ztdPdo->exec(sprintf(
                "INSERT INTO %s (name, age) VALUES ('Diana', 28)",
                $table
            ));

            $stmt = $rawPdo->query(sprintf('SELECT * FROM %s ORDER BY id', $table));
            self::assertNotFalse($stmt);
            /** @var list<array<string, mixed>> */
            $rawRows = $stmt->fetchAll();

            self::assertCount(2, $rawRows);
            self::assertSame('Alice', $rawRows[0]['name']);
            self::assertSame('Bob', $rawRows[1]['name']);
        } finally {
            $rawPdo->exec(sprintf('DROP SCHEMA IF EXISTS "%s" CASCADE', $schemaName));
        }
    }

    public function testDisableZtdBypassesRewriting(): void
    {
        [$schemaName, $rawPdo] = PostgreSqlContainer::createTestSchema();
        $table = 'prefix_' . bin2hex(random_bytes(8));

        try {
            $rawPdo->exec(sprintf(
                'CREATE TABLE %s (id SERIAL PRIMARY KEY, name TEXT NOT NULL, age INTEGER NOT NULL)',
                $table
            ));
            $rawPdo->exec(sprintf(
                "INSERT INTO %s (name, age) VALUES ('Alice', 30), ('Bob', 25)",
                $table
            ));

            $ztdPdo = ZtdPdo::fromPdo($rawPdo, null);

            $ztdPdo->disableZtd();

            $ztdPdo->exec(sprintf(
                "INSERT INTO %s (name, age) VALUES ('Direct', 40)",
                $table
            ));

            $stmt = $rawPdo->query(sprintf('SELECT * FROM %s', $table));
            self::assertNotFalse($stmt);
            /** @var list<array<string, mixed>> */
            $rawRows = $stmt->fetchAll();

            self::assertCount(3, $rawRows);

            $rawPdo->exec(sprintf("DELETE FROM %s WHERE name = 'Direct'", $table));
        } finally {
            $rawPdo->exec(sprintf('DROP SCHEMA IF EXISTS "%s" CASCADE', $schemaName));
        }
    }

    public function testEnableDisableToggle(): void
    {
        [$schemaName, $rawPdo] = PostgreSqlContainer::createTestSchema();
        $table = 'prefix_' . bin2hex(random_bytes(8));

        try {
            $rawPdo->exec(sprintf(
                'CREATE TABLE %s (id SERIAL PRIMARY KEY, name TEXT NOT NULL, age INTEGER NOT NULL)',
                $table
            ));
            $rawPdo->exec(sprintf(
                "INSERT INTO %s (name, age) VALUES ('Alice', 30), ('Bob', 25)",
                $table
            ));

            $ztdPdo = ZtdPdo::fromPdo($rawPdo, null);

            self::assertTrue($ztdPdo->isZtdEnabled());

            $ztdPdo->disableZtd();
            self::assertFalse($ztdPdo->isZtdEnabled());

            $ztdPdo->enableZtd();
            self::assertTrue($ztdPdo->isZtdEnabled());
        } finally {
            $rawPdo->exec(sprintf('DROP SCHEMA IF EXISTS "%s" CASCADE', $schemaName));
        }
    }

    public function testSelectWithWhereOnShadowData(): void
    {
        [$schemaName, $rawPdo] = PostgreSqlContainer::createTestSchema();
        $table = 'prefix_' . bin2hex(random_bytes(8));

        try {
            $rawPdo->exec(sprintf(
                'CREATE TABLE %s (id SERIAL PRIMARY KEY, name TEXT NOT NULL, age INTEGER NOT NULL)',
                $table
            ));
            $rawPdo->exec(sprintf(
                "INSERT INTO %s (name, age) VALUES ('Alice', 30), ('Bob', 25)",
                $table
            ));

            $ztdPdo = ZtdPdo::fromPdo($rawPdo, null);

            $ztdPdo->exec(sprintf(
                "INSERT INTO %s (name, age) VALUES ('Charlie', 35)",
                $table
            ));
            $ztdPdo->exec(sprintf(
                "INSERT INTO %s (name, age) VALUES ('Diana', 28)",
                $table
            ));

            $stmt = $ztdPdo->query(sprintf("SELECT name FROM %s WHERE age > 30 ORDER BY name", $table));
            self::assertNotFalse($stmt);
            /** @var list<array<string, mixed>> */
            $rows = $stmt->fetchAll();

            $names = array_column($rows, 'name');
            self::assertSame(['Charlie'], $names, 'WHERE age > 30 must return only Charlie(35) from shadow data');
        } finally {
            $rawPdo->exec(sprintf('DROP SCHEMA IF EXISTS "%s" CASCADE', $schemaName));
        }
    }

    public function testUpdateDoesNotModifyPhysicalDatabase(): void
    {
        [$schemaName, $rawPdo] = PostgreSqlContainer::createTestSchema();
        $table = 'prefix_' . bin2hex(random_bytes(8));

        try {
            $rawPdo->exec(sprintf(
                'CREATE TABLE %s (id SERIAL PRIMARY KEY, name TEXT NOT NULL, age INTEGER NOT NULL)',
                $table
            ));
            $rawPdo->exec(sprintf(
                "INSERT INTO %s (name, age) VALUES ('Alice', 30), ('Bob', 25)",
                $table
            ));

            $ztdPdo = ZtdPdo::fromPdo($rawPdo, null);

            $ztdPdo->exec(sprintf(
                "UPDATE %s SET age = 99 WHERE name = 'Alice'",
                $table
            ));

            $stmt = $rawPdo->query(sprintf("SELECT name, age FROM %s WHERE name = 'Alice'", $table));
            self::assertNotFalse($stmt);
            /** @var list<array<string, mixed>> */
            $rawRows = $stmt->fetchAll();

            self::assertCount(1, $rawRows);
            self::assertSame('Alice', $rawRows[0]['name']);
            self::assertSame(30, $rawRows[0]['age']);
        } finally {
            $rawPdo->exec(sprintf('DROP SCHEMA IF EXISTS "%s" CASCADE', $schemaName));
        }
    }

    public function testDeleteDoesNotModifyPhysicalDatabase(): void
    {
        [$schemaName, $rawPdo] = PostgreSqlContainer::createTestSchema();
        $table = 'prefix_' . bin2hex(random_bytes(8));

        try {
            $rawPdo->exec(sprintf(
                'CREATE TABLE %s (id SERIAL PRIMARY KEY, name TEXT NOT NULL, age INTEGER NOT NULL)',
                $table
            ));
            $rawPdo->exec(sprintf(
                "INSERT INTO %s (name, age) VALUES ('Alice', 30), ('Bob', 25)",
                $table
            ));

            $ztdPdo = ZtdPdo::fromPdo($rawPdo, null);

            $ztdPdo->exec(sprintf(
                "DELETE FROM %s WHERE name = 'Alice'",
                $table
            ));

            $stmt = $rawPdo->query(sprintf('SELECT name FROM %s ORDER BY name', $table));
            self::assertNotFalse($stmt);
            /** @var list<array<string, mixed>> */
            $rawRows = $stmt->fetchAll();

            $names = array_column($rawRows, 'name');
            self::assertSame(['Alice', 'Bob'], $names, 'Physical database must be unchanged after ZTD DELETE');
        } finally {
            $rawPdo->exec(sprintf('DROP SCHEMA IF EXISTS "%s" CASCADE', $schemaName));
        }
    }

}
