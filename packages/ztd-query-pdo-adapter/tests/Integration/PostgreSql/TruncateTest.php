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
final class TruncateTest extends TestCase
{
    public function testTruncateTable(): void
    {
        [$schemaName, $rawPdo] = PostgreSqlContainer::createTestSchema();
        $table = 'prefix_' . bin2hex(random_bytes(8));

        try {
            $rawPdo->exec("CREATE TABLE {$table} (id INTEGER PRIMARY KEY, name TEXT NOT NULL, age INTEGER NOT NULL)");
            $rawPdo->exec("INSERT INTO {$table} (id, name, age) VALUES (1, 'Alice', 30), (2, 'Bob', 25), (3, 'Charlie', 35)");

            $ztdPdo = ZtdPdo::fromPdo($rawPdo);

            $ztdPdo->exec("INSERT INTO {$table} (id, name, age) VALUES (1, 'Alice', 30), (2, 'Bob', 25), (3, 'Charlie', 35)");

            $rawPdo->exec("TRUNCATE TABLE {$table}");
            $ztdPdo->exec("TRUNCATE TABLE {$table}");

            $stmt = $rawPdo->query("SELECT * FROM {$table}");
            self::assertNotFalse($stmt);
            /** @var list<array<string, mixed>> */
            $rawRows = $stmt->fetchAll();

            $stmt = $ztdPdo->query("SELECT * FROM {$table}");
            self::assertNotFalse($stmt);
            /** @var list<array<string, mixed>> */
            $ztdRows = $stmt->fetchAll();

            self::assertSame($rawRows, $ztdRows);
        } finally {
            $rawPdo->exec(sprintf('DROP SCHEMA IF EXISTS "%s" CASCADE', $schemaName));
        }
    }

    public function testTruncateWithoutTableKeyword(): void
    {
        [$schemaName, $rawPdo] = PostgreSqlContainer::createTestSchema();
        $table = 'prefix_' . bin2hex(random_bytes(8));

        try {
            $rawPdo->exec("CREATE TABLE {$table} (id INTEGER PRIMARY KEY, name TEXT NOT NULL, age INTEGER NOT NULL)");
            $rawPdo->exec("INSERT INTO {$table} (id, name, age) VALUES (1, 'Alice', 30), (2, 'Bob', 25), (3, 'Charlie', 35)");

            $ztdPdo = ZtdPdo::fromPdo($rawPdo);

            $ztdPdo->exec("INSERT INTO {$table} (id, name, age) VALUES (1, 'Alice', 30), (2, 'Bob', 25), (3, 'Charlie', 35)");

            $rawPdo->exec("TRUNCATE {$table}");
            $ztdPdo->exec("TRUNCATE {$table}");

            $stmt = $rawPdo->query("SELECT * FROM {$table}");
            self::assertNotFalse($stmt);
            /** @var list<array<string, mixed>> */
            $rawRows = $stmt->fetchAll();

            $stmt = $ztdPdo->query("SELECT * FROM {$table}");
            self::assertNotFalse($stmt);
            /** @var list<array<string, mixed>> */
            $ztdRows = $stmt->fetchAll();

            self::assertSame($rawRows, $ztdRows);
        } finally {
            $rawPdo->exec(sprintf('DROP SCHEMA IF EXISTS "%s" CASCADE', $schemaName));
        }
    }

    public function testTruncateDoesNotModifyPhysicalDatabase(): void
    {
        [$schemaName, $rawPdo] = PostgreSqlContainer::createTestSchema();
        $table = 'prefix_' . bin2hex(random_bytes(8));

        try {
            $rawPdo->exec("CREATE TABLE {$table} (id INTEGER PRIMARY KEY, name TEXT NOT NULL, age INTEGER NOT NULL)");
            $rawPdo->exec("INSERT INTO {$table} (id, name, age) VALUES (1, 'Alice', 30), (2, 'Bob', 25), (3, 'Charlie', 35)");

            $ztdPdo = ZtdPdo::fromPdo($rawPdo);

            $ztdPdo->exec("INSERT INTO {$table} (id, name, age) VALUES (1, 'Alice', 30), (2, 'Bob', 25), (3, 'Charlie', 35)");

            $ztdPdo->exec("TRUNCATE TABLE {$table}");

            $stmt = $rawPdo->query("SELECT * FROM {$table}");
            self::assertNotFalse($stmt);
            /** @var list<array<string, mixed>> */
            $rawRows = $stmt->fetchAll();

            self::assertCount(3, $rawRows);
        } finally {
            $rawPdo->exec(sprintf('DROP SCHEMA IF EXISTS "%s" CASCADE', $schemaName));
        }
    }

    public function testTruncateMultipleTables(): void
    {
        [$schemaName, $rawPdo] = PostgreSqlContainer::createTestSchema();
        $alpha = 'prefix_' . bin2hex(random_bytes(8));
        $beta = 'prefix_' . bin2hex(random_bytes(8));

        try {
            $rawPdo->exec("CREATE TABLE {$alpha} (id INTEGER PRIMARY KEY, name TEXT)");
            $rawPdo->exec("CREATE TABLE {$beta} (id INTEGER PRIMARY KEY, name TEXT)");
            $rawPdo->exec("INSERT INTO {$alpha} VALUES (1, 'alpha')");
            $rawPdo->exec("INSERT INTO {$beta} VALUES (2, 'beta')");
            $ztdPdo = ZtdPdo::fromPdo($rawPdo);
            $ztdPdo->exec("INSERT INTO {$alpha} VALUES (1, 'alpha')");
            $ztdPdo->exec("INSERT INTO {$beta} VALUES (2, 'beta')");

            $rawPdo->exec("TRUNCATE TABLE {$alpha}, {$beta}");
            $ztdPdo->exec("TRUNCATE TABLE {$alpha}, {$beta}");

            $rawAlpha = $rawPdo->query("SELECT * FROM {$alpha}");
            $rawBeta = $rawPdo->query("SELECT * FROM {$beta}");
            $shadowAlpha = $ztdPdo->query("SELECT * FROM {$alpha}");
            $shadowBeta = $ztdPdo->query("SELECT * FROM {$beta}");
            self::assertNotFalse($rawAlpha);
            self::assertNotFalse($rawBeta);
            self::assertNotFalse($shadowAlpha);
            self::assertNotFalse($shadowBeta);
            self::assertSame($rawAlpha->fetchAll(), $shadowAlpha->fetchAll());
            self::assertSame($rawBeta->fetchAll(), $shadowBeta->fetchAll());
        } finally {
            $rawPdo->exec(sprintf('DROP SCHEMA IF EXISTS "%s" CASCADE', $schemaName));
        }
    }
}
