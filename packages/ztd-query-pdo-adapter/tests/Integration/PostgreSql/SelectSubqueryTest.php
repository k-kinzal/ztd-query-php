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
final class SelectSubqueryTest extends TestCase
{
    public function testSubqueryInWhere(): void
    {
        [$schemaName, $rawPdo] = PostgreSqlContainer::createTestSchema();
        $users = 'prefix_' . bin2hex(random_bytes(8));
        $orders = 'prefix_' . bin2hex(random_bytes(8));

        try {
            $rawPdo->exec("CREATE TABLE {$users} (id INTEGER PRIMARY KEY, name TEXT NOT NULL)");
            $rawPdo->exec("INSERT INTO {$users} (id, name) VALUES (1, 'Alice'), (2, 'Bob'), (3, 'Charlie')");

            $rawPdo->exec("CREATE TABLE {$orders} (id INTEGER PRIMARY KEY, user_id INTEGER NOT NULL, amount INTEGER NOT NULL)");
            $rawPdo->exec("INSERT INTO {$orders} (id, user_id, amount) VALUES (1, 1, 100), (2, 1, 200), (3, 2, 150)");

            $ztdPdo = ZtdPdo::fromPdo($rawPdo);

            $ztdPdo->exec("INSERT INTO {$users} (id, name) VALUES (1, 'Alice'), (2, 'Bob'), (3, 'Charlie')");
            $ztdPdo->exec("INSERT INTO {$orders} (id, user_id, amount) VALUES (1, 1, 100), (2, 1, 200), (3, 2, 150)");

            $sql = "SELECT * FROM {$users} WHERE id IN (SELECT user_id FROM {$orders}) ORDER BY id";

            $stmt = $rawPdo->query($sql);
            self::assertNotFalse($stmt);
            /** @var list<array<string, mixed>> */
            $rawRows = $stmt->fetchAll();

            $stmt = $ztdPdo->query($sql);
            self::assertNotFalse($stmt);
            /** @var list<array<string, mixed>> */
            $ztdRows = $stmt->fetchAll();

            self::assertSame($rawRows, $ztdRows);
        } finally {
            $rawPdo->exec(sprintf('DROP SCHEMA IF EXISTS "%s" CASCADE', $schemaName));
        }
    }

    public function testScalarSubquery(): void
    {
        [$schemaName, $rawPdo] = PostgreSqlContainer::createTestSchema();
        $users = 'prefix_' . bin2hex(random_bytes(8));
        $orders = 'prefix_' . bin2hex(random_bytes(8));

        try {
            $rawPdo->exec("CREATE TABLE {$users} (id INTEGER PRIMARY KEY, name TEXT NOT NULL)");
            $rawPdo->exec("INSERT INTO {$users} (id, name) VALUES (1, 'Alice'), (2, 'Bob'), (3, 'Charlie')");

            $rawPdo->exec("CREATE TABLE {$orders} (id INTEGER PRIMARY KEY, user_id INTEGER NOT NULL, amount INTEGER NOT NULL)");
            $rawPdo->exec("INSERT INTO {$orders} (id, user_id, amount) VALUES (1, 1, 100), (2, 1, 200), (3, 2, 150)");

            $ztdPdo = ZtdPdo::fromPdo($rawPdo);

            $ztdPdo->exec("INSERT INTO {$users} (id, name) VALUES (1, 'Alice'), (2, 'Bob'), (3, 'Charlie')");
            $ztdPdo->exec("INSERT INTO {$orders} (id, user_id, amount) VALUES (1, 1, 100), (2, 1, 200), (3, 2, 150)");

            $sql = "SELECT name, (SELECT COUNT(*) FROM {$orders} WHERE {$orders}.user_id = {$users}.id) AS order_count FROM {$users} ORDER BY id";

            $stmt = $rawPdo->query($sql);
            self::assertNotFalse($stmt);
            /** @var list<array<string, mixed>> */
            $rawRows = $stmt->fetchAll();

            $stmt = $ztdPdo->query($sql);
            self::assertNotFalse($stmt);
            /** @var list<array<string, mixed>> */
            $ztdRows = $stmt->fetchAll();

            self::assertSame($rawRows, $ztdRows);
        } finally {
            $rawPdo->exec(sprintf('DROP SCHEMA IF EXISTS "%s" CASCADE', $schemaName));
        }
    }

    public function testSubqueryInFrom(): void
    {
        [$schemaName, $rawPdo] = PostgreSqlContainer::createTestSchema();
        $users = 'prefix_' . bin2hex(random_bytes(8));
        $orders = 'prefix_' . bin2hex(random_bytes(8));

        try {
            $rawPdo->exec("CREATE TABLE {$users} (id INTEGER PRIMARY KEY, name TEXT NOT NULL)");
            $rawPdo->exec("INSERT INTO {$users} (id, name) VALUES (1, 'Alice'), (2, 'Bob'), (3, 'Charlie')");

            $rawPdo->exec("CREATE TABLE {$orders} (id INTEGER PRIMARY KEY, user_id INTEGER NOT NULL, amount INTEGER NOT NULL)");
            $rawPdo->exec("INSERT INTO {$orders} (id, user_id, amount) VALUES (1, 1, 100), (2, 1, 200), (3, 2, 150)");

            $ztdPdo = ZtdPdo::fromPdo($rawPdo);

            $ztdPdo->exec("INSERT INTO {$users} (id, name) VALUES (1, 'Alice'), (2, 'Bob'), (3, 'Charlie')");
            $ztdPdo->exec("INSERT INTO {$orders} (id, user_id, amount) VALUES (1, 1, 100), (2, 1, 200), (3, 2, 150)");

            $sql = "SELECT sub.name FROM (SELECT name FROM {$users} WHERE id <= 2) AS sub ORDER BY sub.name";

            $stmt = $rawPdo->query($sql);
            self::assertNotFalse($stmt);
            /** @var list<array<string, mixed>> */
            $rawRows = $stmt->fetchAll();

            $stmt = $ztdPdo->query($sql);
            self::assertNotFalse($stmt);
            /** @var list<array<string, mixed>> */
            $ztdRows = $stmt->fetchAll();

            self::assertSame($rawRows, $ztdRows);
        } finally {
            $rawPdo->exec(sprintf('DROP SCHEMA IF EXISTS "%s" CASCADE', $schemaName));
        }
    }
}
