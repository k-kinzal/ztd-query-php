<?php

declare(strict_types=1);

namespace Tests\Integration\Sqlite;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Adapter\Pdo\ZtdPdo;

/**
 * @requires extension pdo_sqlite
 */
#[CoversNothing]
#[Large]
final class SelectUnionTest extends TestCase
{
    public function testUnion(): void
    {
        $rawPdo = new \PDO('sqlite::memory:', null, null, [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
        ]);
        $rawPdo->exec("CREATE TABLE t1 (id INTEGER PRIMARY KEY, name TEXT NOT NULL)");
        $rawPdo->exec("INSERT INTO t1 (id, name) VALUES (1, 'Alice'), (2, 'Bob')");
        $rawPdo->exec("CREATE TABLE t2 (id INTEGER PRIMARY KEY, name TEXT NOT NULL)");
        $rawPdo->exec("INSERT INTO t2 (id, name) VALUES (2, 'Bob'), (3, 'Charlie')");

        $ztdPdo = ZtdPdo::fromPdo($rawPdo);
        $ztdPdo->exec("INSERT INTO t1 (id, name) VALUES (1, 'Alice'), (2, 'Bob')");
        $ztdPdo->exec("INSERT INTO t2 (id, name) VALUES (2, 'Bob'), (3, 'Charlie')");

        $sql = "SELECT name FROM t1 UNION SELECT name FROM t2 ORDER BY name";

        $stmt = $rawPdo->query($sql);
        self::assertNotFalse($stmt);
        /** @var list<array<string, mixed>> $rawRows */
        $rawRows = $stmt->fetchAll();
        $stmt = $ztdPdo->query($sql);
        self::assertNotFalse($stmt);
        /** @var list<array<string, mixed>> $ztdRows */
        $ztdRows = $stmt->fetchAll();

        self::assertSame($rawRows, $ztdRows);
    }

    public function testUnionAll(): void
    {
        $rawPdo = new \PDO('sqlite::memory:', null, null, [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
        ]);
        $rawPdo->exec("CREATE TABLE t1 (id INTEGER PRIMARY KEY, name TEXT NOT NULL)");
        $rawPdo->exec("INSERT INTO t1 (id, name) VALUES (1, 'Alice'), (2, 'Bob')");
        $rawPdo->exec("CREATE TABLE t2 (id INTEGER PRIMARY KEY, name TEXT NOT NULL)");
        $rawPdo->exec("INSERT INTO t2 (id, name) VALUES (2, 'Bob'), (3, 'Charlie')");

        $ztdPdo = ZtdPdo::fromPdo($rawPdo);
        $ztdPdo->exec("INSERT INTO t1 (id, name) VALUES (1, 'Alice'), (2, 'Bob')");
        $ztdPdo->exec("INSERT INTO t2 (id, name) VALUES (2, 'Bob'), (3, 'Charlie')");

        $sql = "SELECT name FROM t1 UNION ALL SELECT name FROM t2 ORDER BY name";

        $stmt = $rawPdo->query($sql);
        self::assertNotFalse($stmt);
        /** @var list<array<string, mixed>> $rawRows */
        $rawRows = $stmt->fetchAll();
        $stmt = $ztdPdo->query($sql);
        self::assertNotFalse($stmt);
        /** @var list<array<string, mixed>> $ztdRows */
        $ztdRows = $stmt->fetchAll();

        self::assertSame($rawRows, $ztdRows);
    }

    public function testExcept(): void
    {
        $rawPdo = new \PDO('sqlite::memory:', null, null, [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
        ]);
        $rawPdo->exec("CREATE TABLE t1 (id INTEGER PRIMARY KEY, name TEXT NOT NULL)");
        $rawPdo->exec("INSERT INTO t1 (id, name) VALUES (1, 'Alice'), (2, 'Bob')");
        $rawPdo->exec("CREATE TABLE t2 (id INTEGER PRIMARY KEY, name TEXT NOT NULL)");
        $rawPdo->exec("INSERT INTO t2 (id, name) VALUES (2, 'Bob'), (3, 'Charlie')");

        $ztdPdo = ZtdPdo::fromPdo($rawPdo);
        $ztdPdo->exec("INSERT INTO t1 (id, name) VALUES (1, 'Alice'), (2, 'Bob')");
        $ztdPdo->exec("INSERT INTO t2 (id, name) VALUES (2, 'Bob'), (3, 'Charlie')");

        $sql = "SELECT name FROM t1 EXCEPT SELECT name FROM t2 ORDER BY name";

        $stmt = $rawPdo->query($sql);
        self::assertNotFalse($stmt);
        /** @var list<array<string, mixed>> $rawRows */
        $rawRows = $stmt->fetchAll();
        $stmt = $ztdPdo->query($sql);
        self::assertNotFalse($stmt);
        /** @var list<array<string, mixed>> $ztdRows */
        $ztdRows = $stmt->fetchAll();

        self::assertSame($rawRows, $ztdRows);
    }

    public function testIntersect(): void
    {
        $rawPdo = new \PDO('sqlite::memory:', null, null, [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
        ]);
        $rawPdo->exec("CREATE TABLE t1 (id INTEGER PRIMARY KEY, name TEXT NOT NULL)");
        $rawPdo->exec("INSERT INTO t1 (id, name) VALUES (1, 'Alice'), (2, 'Bob')");
        $rawPdo->exec("CREATE TABLE t2 (id INTEGER PRIMARY KEY, name TEXT NOT NULL)");
        $rawPdo->exec("INSERT INTO t2 (id, name) VALUES (2, 'Bob'), (3, 'Charlie')");

        $ztdPdo = ZtdPdo::fromPdo($rawPdo);
        $ztdPdo->exec("INSERT INTO t1 (id, name) VALUES (1, 'Alice'), (2, 'Bob')");
        $ztdPdo->exec("INSERT INTO t2 (id, name) VALUES (2, 'Bob'), (3, 'Charlie')");

        $sql = "SELECT name FROM t1 INTERSECT SELECT name FROM t2 ORDER BY name";

        $stmt = $rawPdo->query($sql);
        self::assertNotFalse($stmt);
        /** @var list<array<string, mixed>> $rawRows */
        $rawRows = $stmt->fetchAll();
        $stmt = $ztdPdo->query($sql);
        self::assertNotFalse($stmt);
        /** @var list<array<string, mixed>> $ztdRows */
        $ztdRows = $stmt->fetchAll();

        self::assertSame($rawRows, $ztdRows);
    }
}
