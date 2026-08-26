<?php

declare(strict_types=1);

namespace Tests\Integration\Sqlite;

use PDO;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Adapter\Pdo\ZtdPdo;
use ZtdQuery\Connection\StatementInterface;

/**
 * @requires extension pdo_sqlite
 *
 * @phpstan-import-type Row from StatementInterface
 */
#[CoversNothing]
#[Large]
final class SelectLimitOffsetTest extends TestCase
{
    public function testLimit(): void
    {
        $rawPdo = new PDO('sqlite::memory:', null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $rawPdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT NOT NULL)');
        $rawPdo->exec("INSERT INTO users (id, name) VALUES (1, 'Alice'), (2, 'Bob'), (3, 'Charlie'), (4, 'Diana'), (5, 'Eve')");

        $ztdPdo = ZtdPdo::fromPdo($rawPdo);
        $ztdPdo->exec("INSERT INTO users (id, name) VALUES (1, 'Alice'), (2, 'Bob'), (3, 'Charlie'), (4, 'Diana'), (5, 'Eve')");

        $stmt = $rawPdo->query('SELECT * FROM users ORDER BY id LIMIT 2');
        self::assertNotFalse($stmt);
        /** @var list<Row> $rawRows */
        $rawRows = $stmt->fetchAll();
        $stmt = $ztdPdo->query('SELECT * FROM users ORDER BY id LIMIT 2');
        self::assertNotFalse($stmt);
        /** @var list<Row> $ztdRows */
        $ztdRows = $stmt->fetchAll();

        self::assertSame($rawRows, $ztdRows);
    }

    public function testLimitOffset(): void
    {
        $rawPdo = new PDO('sqlite::memory:', null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $rawPdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT NOT NULL)');
        $rawPdo->exec("INSERT INTO users (id, name) VALUES (1, 'Alice'), (2, 'Bob'), (3, 'Charlie'), (4, 'Diana'), (5, 'Eve')");

        $ztdPdo = ZtdPdo::fromPdo($rawPdo);
        $ztdPdo->exec("INSERT INTO users (id, name) VALUES (1, 'Alice'), (2, 'Bob'), (3, 'Charlie'), (4, 'Diana'), (5, 'Eve')");

        $stmt = $rawPdo->query('SELECT * FROM users ORDER BY id LIMIT 2 OFFSET 2');
        self::assertNotFalse($stmt);
        /** @var list<Row> $rawRows */
        $rawRows = $stmt->fetchAll();
        $stmt = $ztdPdo->query('SELECT * FROM users ORDER BY id LIMIT 2 OFFSET 2');
        self::assertNotFalse($stmt);
        /** @var list<Row> $ztdRows */
        $ztdRows = $stmt->fetchAll();

        self::assertSame($rawRows, $ztdRows);
    }

    public function testLimitZero(): void
    {
        $rawPdo = new PDO('sqlite::memory:', null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $rawPdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT NOT NULL)');
        $rawPdo->exec("INSERT INTO users (id, name) VALUES (1, 'Alice'), (2, 'Bob'), (3, 'Charlie'), (4, 'Diana'), (5, 'Eve')");

        $ztdPdo = ZtdPdo::fromPdo($rawPdo);
        $ztdPdo->exec("INSERT INTO users (id, name) VALUES (1, 'Alice'), (2, 'Bob'), (3, 'Charlie'), (4, 'Diana'), (5, 'Eve')");

        $stmt = $rawPdo->query('SELECT * FROM users ORDER BY id LIMIT 0');
        self::assertNotFalse($stmt);
        /** @var list<Row> $rawRows */
        $rawRows = $stmt->fetchAll();
        $stmt = $ztdPdo->query('SELECT * FROM users ORDER BY id LIMIT 0');
        self::assertNotFalse($stmt);
        /** @var list<Row> $ztdRows */
        $ztdRows = $stmt->fetchAll();

        self::assertSame($rawRows, $ztdRows);
    }
}
