<?php

declare(strict_types=1);

namespace Tests\Integration\PostgreSql;

use PDO;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;
use Tests\Container\PostgreSqlContainer;
use ZtdQuery\Adapter\Pdo\ZtdPdo;

/**
 * @requires extension pdo_pgsql
 * @group integration
 * @group postgres
 *
 * @phpstan-type Row array<string, mixed>
 */
#[CoversNothing]
#[Large]
final class SelectLimitOffsetTest extends TestCase
{
    public function testLimit(): void
    {
        $containerInstance = \Testcontainers\Testcontainers::run(PostgreSqlContainer::class);
        /** @var PDO $rawPdo */
        $rawPdo = $containerInstance->getData(PDO::class);

        $schemaName = 'ztd_' . bin2hex(random_bytes(8));
        $rawPdo->exec(sprintf('CREATE SCHEMA "%s"', $schemaName));
        $rawPdo->exec(sprintf('SET search_path TO "%s"', $schemaName));

        $table = 'prefix_' . bin2hex(random_bytes(8));

        try {
            $rawPdo->exec("CREATE TABLE {$table} (id INTEGER PRIMARY KEY, name TEXT NOT NULL)");
            $rawPdo->exec("INSERT INTO {$table} (id, name) VALUES (1, 'Alice'), (2, 'Bob'), (3, 'Charlie'), (4, 'Diana'), (5, 'Eve')");

            $ztdPdo = ZtdPdo::fromPdo($rawPdo);

            $ztdPdo->exec("INSERT INTO {$table} (id, name) VALUES (1, 'Alice'), (2, 'Bob'), (3, 'Charlie'), (4, 'Diana'), (5, 'Eve')");

            $stmt = $rawPdo->query("SELECT * FROM {$table} ORDER BY id LIMIT 2");
            self::assertNotFalse($stmt);
            /** @var list<Row> */
            $rawRows = $stmt->fetchAll();

            $stmt = $ztdPdo->query("SELECT * FROM {$table} ORDER BY id LIMIT 2");
            self::assertNotFalse($stmt);
            /** @var list<Row> */
            $ztdRows = $stmt->fetchAll();

            self::assertSame($rawRows, $ztdRows);
        } finally {
            $rawPdo->exec(sprintf('DROP SCHEMA IF EXISTS "%s" CASCADE', $schemaName));
        }
    }

    public function testLimitOffset(): void
    {
        $containerInstance = \Testcontainers\Testcontainers::run(PostgreSqlContainer::class);
        /** @var PDO $rawPdo */
        $rawPdo = $containerInstance->getData(PDO::class);

        $schemaName = 'ztd_' . bin2hex(random_bytes(8));
        $rawPdo->exec(sprintf('CREATE SCHEMA "%s"', $schemaName));
        $rawPdo->exec(sprintf('SET search_path TO "%s"', $schemaName));

        $table = 'prefix_' . bin2hex(random_bytes(8));

        try {
            $rawPdo->exec("CREATE TABLE {$table} (id INTEGER PRIMARY KEY, name TEXT NOT NULL)");
            $rawPdo->exec("INSERT INTO {$table} (id, name) VALUES (1, 'Alice'), (2, 'Bob'), (3, 'Charlie'), (4, 'Diana'), (5, 'Eve')");

            $ztdPdo = ZtdPdo::fromPdo($rawPdo);

            $ztdPdo->exec("INSERT INTO {$table} (id, name) VALUES (1, 'Alice'), (2, 'Bob'), (3, 'Charlie'), (4, 'Diana'), (5, 'Eve')");

            $stmt = $rawPdo->query("SELECT * FROM {$table} ORDER BY id LIMIT 2 OFFSET 2");
            self::assertNotFalse($stmt);
            /** @var list<Row> */
            $rawRows = $stmt->fetchAll();

            $stmt = $ztdPdo->query("SELECT * FROM {$table} ORDER BY id LIMIT 2 OFFSET 2");
            self::assertNotFalse($stmt);
            /** @var list<Row> */
            $ztdRows = $stmt->fetchAll();

            self::assertSame($rawRows, $ztdRows);
        } finally {
            $rawPdo->exec(sprintf('DROP SCHEMA IF EXISTS "%s" CASCADE', $schemaName));
        }
    }
}
