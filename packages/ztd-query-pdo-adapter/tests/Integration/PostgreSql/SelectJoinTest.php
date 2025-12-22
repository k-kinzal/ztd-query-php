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
final class SelectJoinTest extends TestCase
{
    public function testInnerJoin(): void
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

            $sql = "SELECT {$users}.name, {$orders}.amount FROM {$users} INNER JOIN {$orders} ON {$users}.id = {$orders}.user_id ORDER BY {$orders}.id";

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

    public function testLeftJoin(): void
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

            $sql = "SELECT {$users}.name, {$orders}.amount FROM {$users} LEFT JOIN {$orders} ON {$users}.id = {$orders}.user_id ORDER BY {$users}.id, {$orders}.id";

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

    public function testRightJoin(): void
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

            $sql = "SELECT {$users}.name, {$orders}.amount FROM {$users} RIGHT JOIN {$orders} ON {$users}.id = {$orders}.user_id ORDER BY {$orders}.id";

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

    public function testFullJoin(): void
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

            $sql = "SELECT {$users}.name, {$orders}.amount FROM {$users} FULL OUTER JOIN {$orders} ON {$users}.id = {$orders}.user_id ORDER BY {$users}.id NULLS LAST, {$orders}.id NULLS LAST";

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

    public function testCrossJoin(): void
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

            $sql = "SELECT {$users}.name, {$orders}.amount FROM {$users} CROSS JOIN {$orders} ORDER BY {$users}.id, {$orders}.id";

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

    public function testNaturalJoin(): void
    {
        [$schemaName, $rawPdo] = PostgreSqlContainer::createTestSchema();
        $t1 = 'prefix_' . bin2hex(random_bytes(8));
        $t2 = 'prefix_' . bin2hex(random_bytes(8));

        try {
            $rawPdo->exec("CREATE TABLE {$t1} (id INTEGER PRIMARY KEY, val TEXT NOT NULL)");
            $rawPdo->exec("INSERT INTO {$t1} (id, val) VALUES (1, 'a'), (2, 'b')");

            $rawPdo->exec("CREATE TABLE {$t2} (id INTEGER PRIMARY KEY, score INTEGER NOT NULL)");
            $rawPdo->exec("INSERT INTO {$t2} (id, score) VALUES (1, 100), (2, 200)");

            $ztdPdo = ZtdPdo::fromPdo($rawPdo);

            $ztdPdo->exec("INSERT INTO {$t1} (id, val) VALUES (1, 'a'), (2, 'b')");
            $ztdPdo->exec("INSERT INTO {$t2} (id, score) VALUES (1, 100), (2, 200)");

            $sql = "SELECT * FROM {$t1} NATURAL JOIN {$t2} ORDER BY id";

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
