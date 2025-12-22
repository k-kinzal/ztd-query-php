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
final class SelectUnionTest extends TestCase
{
    public function testUnion(): void
    {
        [$schemaName, $rawPdo] = PostgreSqlContainer::createTestSchema();
        $t1 = 'prefix_' . bin2hex(random_bytes(8));
        $t2 = 'prefix_' . bin2hex(random_bytes(8));

        try {
            $rawPdo->exec("CREATE TABLE {$t1} (id INTEGER PRIMARY KEY, name TEXT NOT NULL)");
            $rawPdo->exec("INSERT INTO {$t1} (id, name) VALUES (1, 'Alice'), (2, 'Bob')");

            $rawPdo->exec("CREATE TABLE {$t2} (id INTEGER PRIMARY KEY, name TEXT NOT NULL)");
            $rawPdo->exec("INSERT INTO {$t2} (id, name) VALUES (2, 'Bob'), (3, 'Charlie')");

            $ztdPdo = ZtdPdo::fromPdo($rawPdo);

            $ztdPdo->exec("INSERT INTO {$t1} (id, name) VALUES (1, 'Alice'), (2, 'Bob')");
            $ztdPdo->exec("INSERT INTO {$t2} (id, name) VALUES (2, 'Bob'), (3, 'Charlie')");

            $sql = "SELECT name FROM {$t1} UNION SELECT name FROM {$t2} ORDER BY name";

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

    public function testUnionAll(): void
    {
        [$schemaName, $rawPdo] = PostgreSqlContainer::createTestSchema();
        $t1 = 'prefix_' . bin2hex(random_bytes(8));
        $t2 = 'prefix_' . bin2hex(random_bytes(8));

        try {
            $rawPdo->exec("CREATE TABLE {$t1} (id INTEGER PRIMARY KEY, name TEXT NOT NULL)");
            $rawPdo->exec("INSERT INTO {$t1} (id, name) VALUES (1, 'Alice'), (2, 'Bob')");

            $rawPdo->exec("CREATE TABLE {$t2} (id INTEGER PRIMARY KEY, name TEXT NOT NULL)");
            $rawPdo->exec("INSERT INTO {$t2} (id, name) VALUES (2, 'Bob'), (3, 'Charlie')");

            $ztdPdo = ZtdPdo::fromPdo($rawPdo);

            $ztdPdo->exec("INSERT INTO {$t1} (id, name) VALUES (1, 'Alice'), (2, 'Bob')");
            $ztdPdo->exec("INSERT INTO {$t2} (id, name) VALUES (2, 'Bob'), (3, 'Charlie')");

            $sql = "SELECT name FROM {$t1} UNION ALL SELECT name FROM {$t2} ORDER BY name";

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

    public function testExcept(): void
    {
        [$schemaName, $rawPdo] = PostgreSqlContainer::createTestSchema();
        $t1 = 'prefix_' . bin2hex(random_bytes(8));
        $t2 = 'prefix_' . bin2hex(random_bytes(8));

        try {
            $rawPdo->exec("CREATE TABLE {$t1} (id INTEGER PRIMARY KEY, name TEXT NOT NULL)");
            $rawPdo->exec("INSERT INTO {$t1} (id, name) VALUES (1, 'Alice'), (2, 'Bob')");

            $rawPdo->exec("CREATE TABLE {$t2} (id INTEGER PRIMARY KEY, name TEXT NOT NULL)");
            $rawPdo->exec("INSERT INTO {$t2} (id, name) VALUES (2, 'Bob'), (3, 'Charlie')");

            $ztdPdo = ZtdPdo::fromPdo($rawPdo);

            $ztdPdo->exec("INSERT INTO {$t1} (id, name) VALUES (1, 'Alice'), (2, 'Bob')");
            $ztdPdo->exec("INSERT INTO {$t2} (id, name) VALUES (2, 'Bob'), (3, 'Charlie')");

            $sql = "SELECT name FROM {$t1} EXCEPT SELECT name FROM {$t2} ORDER BY name";

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

    public function testIntersect(): void
    {
        [$schemaName, $rawPdo] = PostgreSqlContainer::createTestSchema();
        $t1 = 'prefix_' . bin2hex(random_bytes(8));
        $t2 = 'prefix_' . bin2hex(random_bytes(8));

        try {
            $rawPdo->exec("CREATE TABLE {$t1} (id INTEGER PRIMARY KEY, name TEXT NOT NULL)");
            $rawPdo->exec("INSERT INTO {$t1} (id, name) VALUES (1, 'Alice'), (2, 'Bob')");

            $rawPdo->exec("CREATE TABLE {$t2} (id INTEGER PRIMARY KEY, name TEXT NOT NULL)");
            $rawPdo->exec("INSERT INTO {$t2} (id, name) VALUES (2, 'Bob'), (3, 'Charlie')");

            $ztdPdo = ZtdPdo::fromPdo($rawPdo);

            $ztdPdo->exec("INSERT INTO {$t1} (id, name) VALUES (1, 'Alice'), (2, 'Bob')");
            $ztdPdo->exec("INSERT INTO {$t2} (id, name) VALUES (2, 'Bob'), (3, 'Charlie')");

            $sql = "SELECT name FROM {$t1} INTERSECT SELECT name FROM {$t2} ORDER BY name";

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
