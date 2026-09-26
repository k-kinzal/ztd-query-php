<?php

declare(strict_types=1);

namespace Tests\Integration\PostgreSql;

use Container\Endpoint;
use Container\PostgreSql16Container;
use PDO;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;
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
final class SelectCteTest extends TestCase
{
    public function testSimpleCte(): void
    {
        $endpoint = \Testcontainers\Testcontainers::run(PostgreSql16Container::class)->getData(Endpoint::class);
        /** @var PDO $rawPdo */
        $rawPdo = new PDO(
            $endpoint->dsn(),
            $endpoint->username,
            $endpoint->password,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC],
        );

        $schemaName = 'ztd_' . bin2hex(random_bytes(8));
        $rawPdo->exec(sprintf('CREATE SCHEMA "%s"', $schemaName));
        $rawPdo->exec(sprintf('SET search_path TO "%s"', $schemaName));

        $table = 'prefix_' . bin2hex(random_bytes(8));

        try {
            $rawPdo->exec("CREATE TABLE {$table} (id INTEGER PRIMARY KEY, name TEXT NOT NULL, age INTEGER NOT NULL)");
            $rawPdo->exec("INSERT INTO {$table} (id, name, age) VALUES (1, 'Alice', 30), (2, 'Bob', 25), (3, 'Charlie', 35)");

            $ztdPdo = ZtdPdo::fromPdo($rawPdo);

            $ztdPdo->exec("INSERT INTO {$table} (id, name, age) VALUES (1, 'Alice', 30), (2, 'Bob', 25), (3, 'Charlie', 35)");

            $sql = "WITH seniors AS (SELECT * FROM {$table} WHERE age >= 30) SELECT * FROM seniors ORDER BY id";

            $stmt = $rawPdo->query($sql);
            self::assertNotFalse($stmt);
            /** @var list<Row> */
            $rawRows = $stmt->fetchAll();

            $stmt = $ztdPdo->query($sql);
            self::assertNotFalse($stmt);
            /** @var list<Row> */
            $ztdRows = $stmt->fetchAll();

            self::assertSame($rawRows, $ztdRows);
        } finally {
            $rawPdo->exec(sprintf('DROP SCHEMA IF EXISTS "%s" CASCADE', $schemaName));
        }
    }

    public function testMultipleCtes(): void
    {
        $endpoint = \Testcontainers\Testcontainers::run(PostgreSql16Container::class)->getData(Endpoint::class);
        /** @var PDO $rawPdo */
        $rawPdo = new PDO(
            $endpoint->dsn(),
            $endpoint->username,
            $endpoint->password,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC],
        );

        $schemaName = 'ztd_' . bin2hex(random_bytes(8));
        $rawPdo->exec(sprintf('CREATE SCHEMA "%s"', $schemaName));
        $rawPdo->exec(sprintf('SET search_path TO "%s"', $schemaName));

        $table = 'prefix_' . bin2hex(random_bytes(8));

        try {
            $rawPdo->exec("CREATE TABLE {$table} (id INTEGER PRIMARY KEY, name TEXT NOT NULL, age INTEGER NOT NULL)");
            $rawPdo->exec("INSERT INTO {$table} (id, name, age) VALUES (1, 'Alice', 30), (2, 'Bob', 25), (3, 'Charlie', 35)");

            $ztdPdo = ZtdPdo::fromPdo($rawPdo);

            $ztdPdo->exec("INSERT INTO {$table} (id, name, age) VALUES (1, 'Alice', 30), (2, 'Bob', 25), (3, 'Charlie', 35)");

            $sql = "WITH young AS (SELECT * FROM {$table} WHERE age < 30), old AS (SELECT * FROM {$table} WHERE age >= 30) SELECT * FROM young UNION ALL SELECT * FROM old ORDER BY id";

            $stmt = $rawPdo->query($sql);
            self::assertNotFalse($stmt);
            /** @var list<Row> */
            $rawRows = $stmt->fetchAll();

            $stmt = $ztdPdo->query($sql);
            self::assertNotFalse($stmt);
            /** @var list<Row> */
            $ztdRows = $stmt->fetchAll();

            self::assertSame($rawRows, $ztdRows);
        } finally {
            $rawPdo->exec(sprintf('DROP SCHEMA IF EXISTS "%s" CASCADE', $schemaName));
        }
    }
}
