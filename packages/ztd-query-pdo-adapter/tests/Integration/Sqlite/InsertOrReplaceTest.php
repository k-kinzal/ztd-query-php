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
final class InsertOrReplaceTest extends TestCase
{
    public function testInsertOrReplace(): void
    {
        $rawPdo = new \PDO('sqlite::memory:', null, null, [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
        ]);
        $rawPdo->exec("CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT NOT NULL, age INTEGER NOT NULL)");

        $ztdPdo = ZtdPdo::fromPdo($rawPdo);

        $rawPdo->exec("INSERT INTO users (id, name, age) VALUES (1, 'Alice', 30)");
        $ztdPdo->exec("INSERT INTO users (id, name, age) VALUES (1, 'Alice', 30)");

        $rawPdo->exec("INSERT OR REPLACE INTO users (id, name, age) VALUES (1, 'Alice Updated', 31)");
        $ztdPdo->exec("INSERT OR REPLACE INTO users (id, name, age) VALUES (1, 'Alice Updated', 31)");

        $stmt = $rawPdo->query("SELECT * FROM users ORDER BY id");
        self::assertNotFalse($stmt);
        /** @var list<array<string, mixed>> $rawRows */
        $rawRows = $stmt->fetchAll();
        $stmt = $ztdPdo->query("SELECT * FROM users ORDER BY id");
        self::assertNotFalse($stmt);
        /** @var list<array<string, mixed>> $ztdRows */
        $ztdRows = $stmt->fetchAll();

        self::assertSame($rawRows, $ztdRows);
    }

    public function testReplaceInto(): void
    {
        $rawPdo = new \PDO('sqlite::memory:', null, null, [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
        ]);
        $rawPdo->exec("CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT NOT NULL, age INTEGER NOT NULL)");

        $ztdPdo = ZtdPdo::fromPdo($rawPdo);

        $rawPdo->exec("INSERT INTO users (id, name, age) VALUES (1, 'Alice', 30)");
        $ztdPdo->exec("INSERT INTO users (id, name, age) VALUES (1, 'Alice', 30)");

        $rawPdo->exec("REPLACE INTO users (id, name, age) VALUES (1, 'Bob', 25)");
        $ztdPdo->exec("REPLACE INTO users (id, name, age) VALUES (1, 'Bob', 25)");

        $stmt = $rawPdo->query("SELECT * FROM users ORDER BY id");
        self::assertNotFalse($stmt);
        /** @var list<array<string, mixed>> $rawRows */
        $rawRows = $stmt->fetchAll();
        $stmt = $ztdPdo->query("SELECT * FROM users ORDER BY id");
        self::assertNotFalse($stmt);
        /** @var list<array<string, mixed>> $ztdRows */
        $ztdRows = $stmt->fetchAll();

        self::assertSame($rawRows, $ztdRows);
    }

    public function testReplaceRemovesRowsConflictingWithPrimaryAndUniqueKeys(): void
    {
        $rawPdo = new \PDO('sqlite::memory:', null, null, [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
        ]);
        $rawPdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, email TEXT UNIQUE, name TEXT)');
        $ztdPdo = ZtdPdo::fromPdo($rawPdo);

        $firstInsert = "INSERT INTO users VALUES (1, 'alice@example.com', 'Alice')";
        $secondInsert = "INSERT INTO users VALUES (2, 'bob@example.com', 'Bob')";
        $replace = "REPLACE INTO users VALUES (1, 'bob@example.com', 'Replacement')";
        $rawPdo->exec($firstInsert);
        $ztdPdo->exec($firstInsert);
        $rawPdo->exec($secondInsert);
        $ztdPdo->exec($secondInsert);
        $rawPdo->exec($replace);
        $ztdPdo->exec($replace);

        $rawStatement = $rawPdo->query('SELECT * FROM users ORDER BY id');
        $ztdStatement = $ztdPdo->query('SELECT * FROM users ORDER BY id');
        self::assertNotFalse($rawStatement);
        self::assertNotFalse($ztdStatement);
        self::assertSame($rawStatement->fetchAll(), $ztdStatement->fetchAll());
    }
}
