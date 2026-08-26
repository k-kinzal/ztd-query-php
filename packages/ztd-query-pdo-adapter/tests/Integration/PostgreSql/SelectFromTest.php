<?php

declare(strict_types=1);

namespace Tests\Integration\PostgreSql;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;
use Tests\Fixtures\PostgreSqlContainer;
use ZtdQuery\Adapter\Pdo\ZtdPdo;
use ZtdQuery\Connection\StatementInterface;

/**
 * @requires extension pdo_pgsql
 * @group integration
 * @group postgres
 *
 * @phpstan-import-type Row from StatementInterface
 */
#[CoversNothing]
#[Large]
final class SelectFromTest extends TestCase
{
    public function testSelectAllColumns(): void
    {
        [$schemaName, $rawPdo] = PostgreSqlContainer::createTestSchema();
        $table = 'prefix_' . bin2hex(random_bytes(8));

        try {
            $rawPdo->exec("CREATE TABLE {$table} (id INTEGER PRIMARY KEY, name TEXT NOT NULL, age INTEGER NOT NULL)");
            $rawPdo->exec("INSERT INTO {$table} (id, name, age) VALUES (1, 'Alice', 30), (2, 'Bob', 25), (3, 'Charlie', 35)");

            $ztdPdo = ZtdPdo::fromPdo($rawPdo);

            $ztdPdo->exec("INSERT INTO {$table} (id, name, age) VALUES (1, 'Alice', 30), (2, 'Bob', 25), (3, 'Charlie', 35)");

            $stmt = $rawPdo->query("SELECT * FROM {$table} ORDER BY id");
            self::assertNotFalse($stmt);
            /** @var list<Row> */
            $rawRows = $stmt->fetchAll();

            $stmt = $ztdPdo->query("SELECT * FROM {$table} ORDER BY id");
            self::assertNotFalse($stmt);
            /** @var list<Row> */
            $ztdRows = $stmt->fetchAll();

            self::assertSame($rawRows, $ztdRows);
        } finally {
            $rawPdo->exec(sprintf('DROP SCHEMA IF EXISTS "%s" CASCADE', $schemaName));
        }
    }

    public function testSelectSpecificColumns(): void
    {
        [$schemaName, $rawPdo] = PostgreSqlContainer::createTestSchema();
        $table = 'prefix_' . bin2hex(random_bytes(8));

        try {
            $rawPdo->exec("CREATE TABLE {$table} (id INTEGER PRIMARY KEY, name TEXT NOT NULL, age INTEGER NOT NULL)");
            $rawPdo->exec("INSERT INTO {$table} (id, name, age) VALUES (1, 'Alice', 30), (2, 'Bob', 25), (3, 'Charlie', 35)");

            $ztdPdo = ZtdPdo::fromPdo($rawPdo);

            $ztdPdo->exec("INSERT INTO {$table} (id, name, age) VALUES (1, 'Alice', 30), (2, 'Bob', 25), (3, 'Charlie', 35)");

            $stmt = $rawPdo->query("SELECT name, age FROM {$table} ORDER BY id");
            self::assertNotFalse($stmt);
            /** @var list<Row> */
            $rawRows = $stmt->fetchAll();

            $stmt = $ztdPdo->query("SELECT name, age FROM {$table} ORDER BY id");
            self::assertNotFalse($stmt);
            /** @var list<Row> */
            $ztdRows = $stmt->fetchAll();

            self::assertSame($rawRows, $ztdRows);
        } finally {
            $rawPdo->exec(sprintf('DROP SCHEMA IF EXISTS "%s" CASCADE', $schemaName));
        }
    }

    public function testSelectWithAlias(): void
    {
        [$schemaName, $rawPdo] = PostgreSqlContainer::createTestSchema();
        $table = 'prefix_' . bin2hex(random_bytes(8));

        try {
            $rawPdo->exec("CREATE TABLE {$table} (id INTEGER PRIMARY KEY, name TEXT NOT NULL, age INTEGER NOT NULL)");
            $rawPdo->exec("INSERT INTO {$table} (id, name, age) VALUES (1, 'Alice', 30), (2, 'Bob', 25), (3, 'Charlie', 35)");

            $ztdPdo = ZtdPdo::fromPdo($rawPdo);

            $ztdPdo->exec("INSERT INTO {$table} (id, name, age) VALUES (1, 'Alice', 30), (2, 'Bob', 25), (3, 'Charlie', 35)");

            $stmt = $rawPdo->query("SELECT name AS user_name, age AS user_age FROM {$table} ORDER BY id");
            self::assertNotFalse($stmt);
            /** @var list<Row> */
            $rawRows = $stmt->fetchAll();

            $stmt = $ztdPdo->query("SELECT name AS user_name, age AS user_age FROM {$table} ORDER BY id");
            self::assertNotFalse($stmt);
            /** @var list<Row> */
            $ztdRows = $stmt->fetchAll();

            self::assertSame($rawRows, $ztdRows);
        } finally {
            $rawPdo->exec(sprintf('DROP SCHEMA IF EXISTS "%s" CASCADE', $schemaName));
        }
    }

    public function testSelectWithTableAlias(): void
    {
        [$schemaName, $rawPdo] = PostgreSqlContainer::createTestSchema();
        $table = 'prefix_' . bin2hex(random_bytes(8));

        try {
            $rawPdo->exec("CREATE TABLE {$table} (id INTEGER PRIMARY KEY, name TEXT NOT NULL, age INTEGER NOT NULL)");
            $rawPdo->exec("INSERT INTO {$table} (id, name, age) VALUES (1, 'Alice', 30), (2, 'Bob', 25), (3, 'Charlie', 35)");

            $ztdPdo = ZtdPdo::fromPdo($rawPdo);

            $ztdPdo->exec("INSERT INTO {$table} (id, name, age) VALUES (1, 'Alice', 30), (2, 'Bob', 25), (3, 'Charlie', 35)");

            $stmt = $rawPdo->query("SELECT u.name, u.age FROM {$table} u ORDER BY u.id");
            self::assertNotFalse($stmt);
            /** @var list<Row> */
            $rawRows = $stmt->fetchAll();

            $stmt = $ztdPdo->query("SELECT u.name, u.age FROM {$table} u ORDER BY u.id");
            self::assertNotFalse($stmt);
            /** @var list<Row> */
            $ztdRows = $stmt->fetchAll();

            self::assertSame($rawRows, $ztdRows);
        } finally {
            $rawPdo->exec(sprintf('DROP SCHEMA IF EXISTS "%s" CASCADE', $schemaName));
        }
    }

    public function testFromKeywordInsideExtractIsNotTreatedAsTableClause(): void
    {
        [$schemaName, $rawPdo] = PostgreSqlContainer::createTestSchema();
        $table = 'prefix_' . bin2hex(random_bytes(8));

        try {
            $rawPdo->exec("CREATE TABLE {$table} (id INTEGER PRIMARY KEY, event_date DATE NOT NULL)");
            $ztdPdo = ZtdPdo::fromPdo($rawPdo);
            $ztdPdo->exec("INSERT INTO {$table} (id, event_date) VALUES (1, '2024-01-10')");

            $statement = $ztdPdo->query("SELECT EXTRACT(YEAR FROM event_date) AS year FROM {$table} WHERE id = 1");

            self::assertNotFalse($statement);
            self::assertSame('2024', $statement->fetchColumn());
        } finally {
            $rawPdo->exec(sprintf('DROP SCHEMA IF EXISTS "%s" CASCADE', $schemaName));
        }
    }
}
