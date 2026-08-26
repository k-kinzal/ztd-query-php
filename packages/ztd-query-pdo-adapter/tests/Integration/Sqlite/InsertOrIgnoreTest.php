<?php

declare(strict_types=1);

namespace Tests\Integration\Sqlite;

use PDO;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Adapter\Pdo\ZtdPdo;

/**
 * @requires extension pdo_sqlite
 */
#[CoversNothing]
#[Large]
final class InsertOrIgnoreTest extends TestCase
{
    public function testInsertOrIgnoreDuplicateKey(): void
    {
        $rawPdo = new PDO('sqlite::memory:', null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $rawPdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT NOT NULL, age INTEGER NOT NULL)');

        $ztdPdo = ZtdPdo::fromPdo($rawPdo);

        $rawPdo->exec("INSERT INTO users (id, name, age) VALUES (1, 'Alice', 30)");
        $ztdPdo->exec("INSERT INTO users (id, name, age) VALUES (1, 'Alice', 30)");

        $rawPdo->exec("INSERT OR IGNORE INTO users (id, name, age) VALUES (1, 'Alice Duplicate', 31)");
        $ztdPdo->exec("INSERT OR IGNORE INTO users (id, name, age) VALUES (1, 'Alice Duplicate', 31)");

        $stmt = $rawPdo->query('SELECT * FROM users ORDER BY id');
        self::assertNotFalse($stmt);
        /** @var list<array<string, mixed>> $rawRows */
        $rawRows = $stmt->fetchAll();
        $stmt = $ztdPdo->query('SELECT * FROM users ORDER BY id');
        self::assertNotFalse($stmt);
        /** @var list<array<string, mixed>> $ztdRows */
        $ztdRows = $stmt->fetchAll();

        self::assertSame($rawRows, $ztdRows);
    }

    public function testInsertOrIgnoreNewRow(): void
    {
        $rawPdo = new PDO('sqlite::memory:', null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $rawPdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT NOT NULL, age INTEGER NOT NULL)');

        $ztdPdo = ZtdPdo::fromPdo($rawPdo);

        $rawPdo->exec("INSERT INTO users (id, name, age) VALUES (1, 'Alice', 30)");
        $ztdPdo->exec("INSERT INTO users (id, name, age) VALUES (1, 'Alice', 30)");

        $rawPdo->exec("INSERT OR IGNORE INTO users (id, name, age) VALUES (2, 'Bob', 25)");
        $ztdPdo->exec("INSERT OR IGNORE INTO users (id, name, age) VALUES (2, 'Bob', 25)");

        $stmt = $rawPdo->query('SELECT * FROM users ORDER BY id');
        self::assertNotFalse($stmt);
        /** @var list<array<string, mixed>> $rawRows */
        $rawRows = $stmt->fetchAll();
        $stmt = $ztdPdo->query('SELECT * FROM users ORDER BY id');
        self::assertNotFalse($stmt);
        /** @var list<array<string, mixed>> $ztdRows */
        $ztdRows = $stmt->fetchAll();

        self::assertSame($rawRows, $ztdRows);
    }

    public function testInsertOrIgnoreUsesUniqueCandidateKeyAndNullSemantics(): void
    {
        $rawPdo = new PDO('sqlite::memory:', null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $rawPdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, email TEXT UNIQUE)');
        $ztdPdo = ZtdPdo::fromPdo($rawPdo);

        $insert = "INSERT INTO users (id, email) VALUES (1, 'alice@example.com')";
        $uniqueConflict = "INSERT OR IGNORE INTO users (id, email) VALUES (2, 'alice@example.com')";
        $firstNull = 'INSERT OR IGNORE INTO users (id, email) VALUES (3, NULL)';
        $secondNull = 'INSERT OR IGNORE INTO users (id, email) VALUES (4, NULL)';
        $rawPdo->exec($insert);
        $ztdPdo->exec($insert);
        $rawPdo->exec($uniqueConflict);
        $ztdPdo->exec($uniqueConflict);
        $rawPdo->exec($firstNull);
        $ztdPdo->exec($firstNull);
        $rawPdo->exec($secondNull);
        $ztdPdo->exec($secondNull);

        $rawStatement = $rawPdo->query('SELECT * FROM users ORDER BY id');
        $ztdStatement = $ztdPdo->query('SELECT * FROM users ORDER BY id');
        self::assertNotFalse($rawStatement);
        self::assertNotFalse($ztdStatement);
        self::assertSame($rawStatement->fetchAll(), $ztdStatement->fetchAll());
    }
}
