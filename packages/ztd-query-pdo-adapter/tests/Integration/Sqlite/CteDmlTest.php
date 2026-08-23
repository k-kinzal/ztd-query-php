<?php

declare(strict_types=1);

namespace Tests\Integration\Sqlite;

use PDO;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Adapter\Pdo\ZtdPdo;

#[CoversNothing]
#[Large]
final class CteDmlTest extends TestCase
{
    public function testCteDefinitionsRemainVisibleToRewrittenInsertUpdateAndDelete(): void
    {
        $raw = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $raw->exec('CREATE TABLE items (id INTEGER PRIMARY KEY, value TEXT)');
        $ztdPdo = ZtdPdo::fromPdo($raw);

        self::assertSame(2, $ztdPdo->exec("WITH source(id, value) AS (VALUES (1, 'one'), (2, 'two')) INSERT INTO items SELECT * FROM source"));
        self::assertSame(1, $ztdPdo->exec("WITH chosen AS (SELECT id FROM items WHERE value = 'two') UPDATE items SET value = 'changed' WHERE id IN (SELECT id FROM chosen)"));
        self::assertSame(1, $ztdPdo->exec("WITH chosen AS (SELECT id FROM items WHERE value = 'one') DELETE FROM items WHERE id IN (SELECT id FROM chosen)"));

        $statement = $ztdPdo->query('SELECT * FROM items');
        self::assertNotFalse($statement);
        self::assertSame([['id' => 2, 'value' => 'changed']], $statement->fetchAll(PDO::FETCH_ASSOC));
    }
}
