<?php

declare(strict_types=1);

namespace Tests\Integration;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Adapter\Pdo\ZtdPdo;

/**
 * Integration tests for ZtdPdo with SQLite: CTE shadowing behavior.
 *
 * ZTD CTE shadowing replaces real table references with CTE definitions
 * that contain only the shadow data (mutations made through ZTD).
 * When no mutations exist for a table, the CTE returns zero rows.
 * Mutations accumulate in the shadow store: INSERT adds rows,
 * UPDATE and DELETE operate on shadow rows only.
 *
 * @requires extension pdo_sqlite
 */
#[CoversNothing]
#[Large]
final class SqliteCteShadowingTest extends TestCase
{
    public function testSelectOnCleanShadowReturnsEmpty(): void
    {
        $rawPdo = new \PDO('sqlite::memory:', null, null, [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION, \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC]);
        $rawPdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL, age INTEGER NOT NULL)');
        $rawPdo->exec("INSERT INTO users (name, age) VALUES ('Alice', 30), ('Bob', 25)");

        $ztdPdo = ZtdPdo::fromPdo($rawPdo, null);

        $stmt = $ztdPdo->query('SELECT * FROM users ORDER BY id');
        self::assertNotFalse($stmt);
        /** @var list<array<string, mixed>> $rows */
        $rows = $stmt->fetchAll();
        self::assertCount(0, $rows);
    }

    public function testInsertDoesNotModifyPhysicalDatabase(): void
    {
        $rawPdo = new \PDO('sqlite::memory:', null, null, [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION, \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC]);
        $rawPdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL, age INTEGER NOT NULL)');
        $rawPdo->exec("INSERT INTO users (name, age) VALUES ('Alice', 30), ('Bob', 25)");

        $ztdPdo = ZtdPdo::fromPdo($rawPdo, null);

        $ztdPdo->exec("INSERT INTO users (name, age) VALUES ('Charlie', 35)");

        $stmt = $rawPdo->query('SELECT * FROM users');
        self::assertNotFalse($stmt);
        /** @var list<array<string, mixed>> $rawRows */
        $rawRows = $stmt->fetchAll();
        self::assertCount(2, $rawRows);
    }

    public function testInsertIsVisibleViaZtdSelect(): void
    {
        $rawPdo = new \PDO('sqlite::memory:', null, null, [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION, \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC]);
        $rawPdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL, age INTEGER NOT NULL)');
        $rawPdo->exec("INSERT INTO users (name, age) VALUES ('Alice', 30), ('Bob', 25)");

        $ztdPdo = ZtdPdo::fromPdo($rawPdo, null);

        $ztdPdo->exec("INSERT INTO users (name, age) VALUES ('Charlie', 35)");

        $stmt = $ztdPdo->query('SELECT name, age FROM users ORDER BY name');
        self::assertNotFalse($stmt);
        /** @var list<array<string, mixed>> $ztdRows */
        $ztdRows = $stmt->fetchAll();
        self::assertCount(1, $ztdRows);
        self::assertSame('Charlie', $ztdRows[0]['name']);
        self::assertEquals(35, $ztdRows[0]['age']);
    }

    public function testMultipleInsertsAccumulate(): void
    {
        $rawPdo = new \PDO('sqlite::memory:', null, null, [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION, \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC]);
        $rawPdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL, age INTEGER NOT NULL)');
        $rawPdo->exec("INSERT INTO users (name, age) VALUES ('Alice', 30), ('Bob', 25)");

        $ztdPdo = ZtdPdo::fromPdo($rawPdo, null);

        $ztdPdo->exec("INSERT INTO users (name, age) VALUES ('Charlie', 35)");
        $ztdPdo->exec("INSERT INTO users (name, age) VALUES ('Diana', 28)");

        $stmt = $ztdPdo->query('SELECT name FROM users ORDER BY name');
        self::assertNotFalse($stmt);
        /** @var list<array<string, mixed>> $ztdRows */
        $ztdRows = $stmt->fetchAll();
        self::assertCount(2, $ztdRows);

        $names = array_column($ztdRows, 'name');
        self::assertSame(['Charlie', 'Diana'], $names, 'Both inserted names must appear in exact order');
    }

    public function testSelectWithWhereOnShadowData(): void
    {
        $rawPdo = new \PDO('sqlite::memory:', null, null, [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION, \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC]);
        $rawPdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL, age INTEGER NOT NULL)');
        $rawPdo->exec("INSERT INTO users (name, age) VALUES ('Alice', 30), ('Bob', 25)");

        $ztdPdo = ZtdPdo::fromPdo($rawPdo, null);

        $ztdPdo->exec("INSERT INTO users (name, age) VALUES ('Charlie', 35)");
        $ztdPdo->exec("INSERT INTO users (name, age) VALUES ('Diana', 28)");

        $stmt = $ztdPdo->query("SELECT * FROM users WHERE age > 30");
        self::assertNotFalse($stmt);
        /** @var list<array<string, mixed>> $rows */
        $rows = $stmt->fetchAll();
        self::assertCount(1, $rows);
        self::assertSame('Charlie', $rows[0]['name']);
    }

    public function testPhysicalDatabaseRemainsUnchangedAfterMutations(): void
    {
        $rawPdo = new \PDO('sqlite::memory:', null, null, [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION, \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC]);
        $rawPdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL, age INTEGER NOT NULL)');
        $rawPdo->exec("INSERT INTO users (name, age) VALUES ('Alice', 30), ('Bob', 25)");

        $ztdPdo = ZtdPdo::fromPdo($rawPdo, null);

        $ztdPdo->exec("INSERT INTO users (name, age) VALUES ('Charlie', 35)");
        $ztdPdo->exec("INSERT INTO users (name, age) VALUES ('Diana', 28)");

        $stmt = $rawPdo->query('SELECT * FROM users ORDER BY id');
        self::assertNotFalse($stmt);
        /** @var list<array<string, mixed>> $rawRows */
        $rawRows = $stmt->fetchAll();
        self::assertCount(2, $rawRows);
        self::assertSame('Alice', $rawRows[0]['name']);
        self::assertSame('Bob', $rawRows[1]['name']);
    }

    public function testDisableZtdBypassesRewriting(): void
    {
        $rawPdo = new \PDO('sqlite::memory:', null, null, [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION, \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC]);
        $rawPdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL, age INTEGER NOT NULL)');
        $rawPdo->exec("INSERT INTO users (name, age) VALUES ('Alice', 30), ('Bob', 25)");

        $ztdPdo = ZtdPdo::fromPdo($rawPdo, null);
        $ztdPdo->disableZtd();

        $ztdPdo->exec("INSERT INTO users (name, age) VALUES ('Direct', 40)");

        $stmt = $rawPdo->query('SELECT * FROM users');
        self::assertNotFalse($stmt);
        /** @var list<array<string, mixed>> $rawRows */
        $rawRows = $stmt->fetchAll();
        self::assertCount(3, $rawRows);

        $rawPdo->exec("DELETE FROM users WHERE name = 'Direct'");
    }

    public function testEnableDisableToggle(): void
    {
        $rawPdo = new \PDO('sqlite::memory:', null, null, [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION, \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC]);
        $rawPdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL, age INTEGER NOT NULL)');
        $rawPdo->exec("INSERT INTO users (name, age) VALUES ('Alice', 30), ('Bob', 25)");

        $ztdPdo = ZtdPdo::fromPdo($rawPdo, null);

        self::assertTrue($ztdPdo->isZtdEnabled());
        $ztdPdo->disableZtd();
        self::assertFalse($ztdPdo->isZtdEnabled());
        $ztdPdo->enableZtd();
        self::assertTrue($ztdPdo->isZtdEnabled());
    }

    public function testPreparedStatementSelectWithZtd(): void
    {
        $rawPdo = new \PDO('sqlite::memory:', null, null, [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION, \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC]);
        $rawPdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL, age INTEGER NOT NULL)');
        $rawPdo->exec("INSERT INTO users (name, age) VALUES ('Alice', 30), ('Bob', 25)");

        $ztdPdo = ZtdPdo::fromPdo($rawPdo, null);

        $ztdPdo->exec("INSERT INTO users (name, age) VALUES ('Charlie', 35)");

        $stmt = $ztdPdo->prepare('SELECT * FROM users WHERE name = ?');
        self::assertNotFalse($stmt);

        $stmt->execute(['Charlie']);
        /** @var list<array<string, mixed>> $rows */
        $rows = $stmt->fetchAll();

        self::assertCount(1, $rows);
        self::assertSame('Charlie', $rows[0]['name']);
    }

    public function testPreparedStatementSelectNonExistent(): void
    {
        $rawPdo = new \PDO('sqlite::memory:', null, null, [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION, \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC]);
        $rawPdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL, age INTEGER NOT NULL)');
        $rawPdo->exec("INSERT INTO users (name, age) VALUES ('Alice', 30), ('Bob', 25)");

        $ztdPdo = ZtdPdo::fromPdo($rawPdo, null);

        $ztdPdo->exec("INSERT INTO users (name, age) VALUES ('Charlie', 35)");

        $stmt = $ztdPdo->prepare('SELECT * FROM users WHERE name = ?');
        self::assertNotFalse($stmt);

        $stmt->execute(['Alice']);
        /** @var list<array<string, mixed>> $rows */
        $rows = $stmt->fetchAll();

        self::assertCount(0, $rows);
    }

    public function testMultipleInsertsExactRowComparison(): void
    {
        $rawPdo = new \PDO('sqlite::memory:', null, null, [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION, \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC]);
        $rawPdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL, age INTEGER NOT NULL)');
        $rawPdo->exec("INSERT INTO users (name, age) VALUES ('Alice', 30), ('Bob', 25)");

        $ztdPdo = ZtdPdo::fromPdo($rawPdo, null);

        $ztdPdo->exec("INSERT INTO users (name, age) VALUES ('Charlie', 35)");
        $ztdPdo->exec("INSERT INTO users (name, age) VALUES ('Diana', 28)");

        $stmt = $ztdPdo->query('SELECT name, age FROM users ORDER BY name');
        self::assertNotFalse($stmt);
        /** @var list<array<string, mixed>> $ztdRows */
        $ztdRows = $stmt->fetchAll();
        self::assertCount(2, $ztdRows);
        self::assertSame('Charlie', $ztdRows[0]['name']);
        self::assertSame('Diana', $ztdRows[1]['name']);

        $stmt = $rawPdo->query('SELECT name FROM users ORDER BY name');
        self::assertNotFalse($stmt);
        /** @var list<array<string, mixed>> $rawRows */
        $rawRows = $stmt->fetchAll();
        self::assertCount(2, $rawRows);
        self::assertSame('Alice', $rawRows[0]['name']);
        self::assertSame('Bob', $rawRows[1]['name']);
    }

    public function testUpdateShadowData(): void
    {
        $rawPdo = new \PDO('sqlite::memory:', null, null, [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION, \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC]);
        $rawPdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL, age INTEGER NOT NULL)');
        $rawPdo->exec("INSERT INTO users (name, age) VALUES ('Alice', 30), ('Bob', 25)");

        $ztdPdo = ZtdPdo::fromPdo($rawPdo, null);

        $ztdPdo->exec("INSERT INTO users (id, name, age) VALUES (100, 'Charlie', 35)");
        $ztdPdo->exec("INSERT INTO users (id, name, age) VALUES (101, 'Diana', 28)");
        $ztdPdo->exec("UPDATE users SET age = 36 WHERE name = 'Charlie'");

        $stmt = $ztdPdo->query("SELECT name, age FROM users WHERE name = 'Charlie'");
        self::assertNotFalse($stmt);
        /** @var list<array<string, mixed>> $ztdRows */
        $ztdRows = $stmt->fetchAll();
        self::assertCount(1, $ztdRows);
        self::assertSame('Charlie', $ztdRows[0]['name']);
        self::assertEquals(36, $ztdRows[0]['age']);

        $stmt = $ztdPdo->query("SELECT name, age FROM users WHERE name = 'Diana'");
        self::assertNotFalse($stmt);
        /** @var list<array<string, mixed>> $dianaRows */
        $dianaRows = $stmt->fetchAll();
        self::assertCount(1, $dianaRows);
        self::assertSame('Diana', $dianaRows[0]['name']);
        self::assertEquals(28, $dianaRows[0]['age']);

        $stmt = $rawPdo->query('SELECT name, age FROM users ORDER BY id');
        self::assertNotFalse($stmt);
        /** @var list<array<string, mixed>> $rawRows */
        $rawRows = $stmt->fetchAll();
        self::assertCount(2, $rawRows);
        self::assertSame('Alice', $rawRows[0]['name']);
        self::assertSame(30, $rawRows[0]['age']);
        self::assertSame('Bob', $rawRows[1]['name']);
        self::assertSame(25, $rawRows[1]['age']);
    }

    public function testDeleteShadowData(): void
    {
        $rawPdo = new \PDO('sqlite::memory:', null, null, [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION, \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC]);
        $rawPdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL, age INTEGER NOT NULL)');
        $rawPdo->exec("INSERT INTO users (name, age) VALUES ('Alice', 30), ('Bob', 25)");

        $ztdPdo = ZtdPdo::fromPdo($rawPdo, null);

        $ztdPdo->exec("INSERT INTO users (id, name, age) VALUES (100, 'Charlie', 35)");
        $ztdPdo->exec("INSERT INTO users (id, name, age) VALUES (101, 'Diana', 28)");
        $ztdPdo->exec("DELETE FROM users WHERE name = 'Charlie'");

        $stmt = $ztdPdo->query('SELECT name FROM users ORDER BY name');
        self::assertNotFalse($stmt);
        /** @var list<array<string, mixed>> $ztdRows */
        $ztdRows = $stmt->fetchAll();
        self::assertCount(1, $ztdRows);
        self::assertSame('Diana', $ztdRows[0]['name']);

        $stmt = $rawPdo->query('SELECT name FROM users ORDER BY id');
        self::assertNotFalse($stmt);
        /** @var list<array<string, mixed>> $rawRows */
        $rawRows = $stmt->fetchAll();
        self::assertCount(2, $rawRows);
        self::assertSame('Alice', $rawRows[0]['name']);
        self::assertSame('Bob', $rawRows[1]['name']);
    }

    public function testDeleteAllShadowData(): void
    {
        $rawPdo = new \PDO('sqlite::memory:', null, null, [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION, \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC]);
        $rawPdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL, age INTEGER NOT NULL)');
        $rawPdo->exec("INSERT INTO users (name, age) VALUES ('Alice', 30), ('Bob', 25)");

        $ztdPdo = ZtdPdo::fromPdo($rawPdo, null);

        $ztdPdo->exec("INSERT INTO users (id, name, age) VALUES (100, 'Charlie', 35)");
        $ztdPdo->exec("DELETE FROM users WHERE name = 'Charlie'");

        $stmt = $ztdPdo->query('SELECT * FROM users');
        self::assertNotFalse($stmt);
        /** @var list<array<string, mixed>> $ztdRows */
        $ztdRows = $stmt->fetchAll();
        self::assertCount(0, $ztdRows);

        $stmt = $rawPdo->query('SELECT * FROM users');
        self::assertNotFalse($stmt);
        /** @var list<array<string, mixed>> $rawRows */
        $rawRows = $stmt->fetchAll();
        self::assertCount(2, $rawRows);
    }

    public function testInsertThenUpdateThenSelectRoundtrip(): void
    {
        $rawPdo = new \PDO('sqlite::memory:', null, null, [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION, \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC]);
        $rawPdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL, age INTEGER NOT NULL)');
        $rawPdo->exec("INSERT INTO users (name, age) VALUES ('Alice', 30), ('Bob', 25)");

        $ztdPdo = ZtdPdo::fromPdo($rawPdo, null);

        $ztdPdo->exec("INSERT INTO users (id, name, age) VALUES (100, 'Charlie', 35)");
        $ztdPdo->exec("UPDATE users SET name = 'Charles' WHERE name = 'Charlie'");

        $stmt = $ztdPdo->query('SELECT name FROM users');
        self::assertNotFalse($stmt);
        /** @var list<array<string, mixed>> $ztdRows */
        $ztdRows = $stmt->fetchAll();
        self::assertCount(1, $ztdRows);
        self::assertSame('Charles', $ztdRows[0]['name']);

        $stmt = $rawPdo->query('SELECT * FROM users');
        self::assertNotFalse($stmt);
        /** @var list<array<string, mixed>> $rawRows */
        $rawRows = $stmt->fetchAll();
        self::assertCount(2, $rawRows);
    }

    public function testUpdateIsVisibleViaZtdSelect(): void
    {
        $rawPdo = new \PDO('sqlite::memory:', null, null, [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION, \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC]);
        $rawPdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL, age INTEGER NOT NULL)');
        $rawPdo->exec("INSERT INTO users (name, age) VALUES ('Alice', 30), ('Bob', 25)");

        $ztdPdo = ZtdPdo::fromPdo($rawPdo, null);

        $ztdPdo->exec("INSERT INTO users (id, name, age) VALUES (100, 'Charlie', 35)");
        $ztdPdo->exec("UPDATE users SET age = 36 WHERE name = 'Charlie'");

        $stmt = $ztdPdo->query("SELECT name, age FROM users WHERE name = 'Charlie'");
        self::assertNotFalse($stmt);
        /** @var list<array<string, mixed>> $ztdRows */
        $ztdRows = $stmt->fetchAll();
        self::assertCount(1, $ztdRows);
        self::assertSame('Charlie', $ztdRows[0]['name']);
        self::assertEquals(36, $ztdRows[0]['age']);
    }

    public function testUpdateDoesNotModifyPhysicalDatabase(): void
    {
        $rawPdo = new \PDO('sqlite::memory:', null, null, [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION, \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC]);
        $rawPdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL, age INTEGER NOT NULL)');
        $rawPdo->exec("INSERT INTO users (name, age) VALUES ('Alice', 30), ('Bob', 25)");

        $ztdPdo = ZtdPdo::fromPdo($rawPdo, null);

        $ztdPdo->exec("INSERT INTO users (id, name, age) VALUES (100, 'Charlie', 35)");
        $ztdPdo->exec("UPDATE users SET age = 99 WHERE name = 'Charlie'");

        $stmt = $rawPdo->query('SELECT name, age FROM users ORDER BY id');
        self::assertNotFalse($stmt);
        /** @var list<array<string, mixed>> $rawRows */
        $rawRows = $stmt->fetchAll();
        self::assertCount(2, $rawRows);
        self::assertSame('Alice', $rawRows[0]['name']);
        self::assertSame(30, $rawRows[0]['age']);
        self::assertSame('Bob', $rawRows[1]['name']);
        self::assertSame(25, $rawRows[1]['age']);
    }

    public function testDeleteIsVisibleViaZtdSelect(): void
    {
        $rawPdo = new \PDO('sqlite::memory:', null, null, [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION, \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC]);
        $rawPdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL, age INTEGER NOT NULL)');
        $rawPdo->exec("INSERT INTO users (name, age) VALUES ('Alice', 30), ('Bob', 25)");

        $ztdPdo = ZtdPdo::fromPdo($rawPdo, null);

        $ztdPdo->exec("INSERT INTO users (id, name, age) VALUES (100, 'Charlie', 35)");
        $ztdPdo->exec("INSERT INTO users (id, name, age) VALUES (101, 'Diana', 28)");
        $ztdPdo->exec("DELETE FROM users WHERE name = 'Charlie'");

        $stmt = $ztdPdo->query('SELECT name FROM users ORDER BY name');
        self::assertNotFalse($stmt);
        /** @var list<array<string, mixed>> $ztdRows */
        $ztdRows = $stmt->fetchAll();
        $names = array_column($ztdRows, 'name');
        self::assertSame(['Diana'], $names, 'After DELETE, only Diana must remain in ZTD view');
    }

    public function testDeleteDoesNotModifyPhysicalDatabase(): void
    {
        $rawPdo = new \PDO('sqlite::memory:', null, null, [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION, \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC]);
        $rawPdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL, age INTEGER NOT NULL)');
        $rawPdo->exec("INSERT INTO users (name, age) VALUES ('Alice', 30), ('Bob', 25)");

        $ztdPdo = ZtdPdo::fromPdo($rawPdo, null);

        $ztdPdo->exec("INSERT INTO users (id, name, age) VALUES (100, 'Charlie', 35)");
        $ztdPdo->exec("DELETE FROM users WHERE name = 'Charlie'");

        $stmt = $rawPdo->query('SELECT name FROM users ORDER BY id');
        self::assertNotFalse($stmt);
        /** @var list<array<string, mixed>> $rawRows */
        $rawRows = $stmt->fetchAll();
        $names = array_column($rawRows, 'name');
        self::assertSame(['Alice', 'Bob'], $names, 'Physical database must be unchanged after ZTD DELETE');
    }

    public function testInsertWithNullValues(): void
    {
        $rawPdo = new \PDO('sqlite::memory:', null, null, [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION, \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC]);
        $rawPdo->exec('CREATE TABLE nullable_table (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT, bio TEXT)');

        $ztdPdo = ZtdPdo::fromPdo($rawPdo, null);

        $ztdPdo->exec("INSERT INTO nullable_table (name, bio) VALUES ('Test', NULL)");

        $ztdRows = $ztdPdo->query('SELECT * FROM nullable_table');
        self::assertNotFalse($ztdRows);

        /** @var list<array<string, mixed>> $rows */
        $rows = $ztdRows->fetchAll();
        self::assertCount(1, $rows);
        self::assertSame('Test', $rows[0]['name']);
        self::assertNull($rows[0]['bio']);
    }
}
