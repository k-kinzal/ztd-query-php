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
final class UpsertExpressionTest extends TestCase
{
    public function testSelfReferencingUpsertMatchesNativeSqlite(): void
    {
        $rawPdo = new \PDO('sqlite::memory:', null, null, [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
        ]);
        $rawPdo->exec('CREATE TABLE native_items (id INTEGER PRIMARY KEY, quantity INTEGER NOT NULL)');
        $rawPdo->exec('CREATE TABLE shadow_items (id INTEGER PRIMARY KEY, quantity INTEGER NOT NULL)');
        $ztdPdo = ZtdPdo::fromPdo($rawPdo);

        $rawPdo->exec('INSERT INTO native_items VALUES (1, 100)');
        $ztdPdo->exec('INSERT INTO shadow_items VALUES (1, 100)');
        $rawPdo->exec('INSERT INTO native_items VALUES (1, 5), (1, 7) ON CONFLICT(id) DO UPDATE SET quantity = native_items.quantity + excluded.quantity');
        $ztdPdo->exec('INSERT INTO shadow_items VALUES (1, 5), (1, 7) ON CONFLICT(id) DO UPDATE SET quantity = shadow_items.quantity + excluded.quantity');

        $rawStatement = $rawPdo->query('SELECT id, quantity FROM native_items');
        $ztdStatement = $ztdPdo->query('SELECT id, quantity FROM shadow_items');
        $physicalShadowStatement = $rawPdo->query('SELECT * FROM shadow_items');
        self::assertNotFalse($rawStatement);
        self::assertNotFalse($ztdStatement);
        self::assertNotFalse($physicalShadowStatement);
        self::assertSame($rawStatement->fetchAll(), $ztdStatement->fetchAll());
        self::assertSame([], $physicalShadowStatement->fetchAll());
    }

    public function testConditionalUpsertAndReturningMatchNativeSqlite(): void
    {
        $rawPdo = new \PDO('sqlite::memory:', null, null, [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
        ]);
        $rawPdo->exec('CREATE TABLE native_items (id INTEGER PRIMARY KEY, name TEXT, score INTEGER)');
        $rawPdo->exec('CREATE TABLE shadow_items (id INTEGER PRIMARY KEY, name TEXT, score INTEGER)');
        $ztdPdo = ZtdPdo::fromPdo($rawPdo);
        $rawPdo->exec("INSERT INTO native_items VALUES (1, 'original', 50)");
        $ztdPdo->exec("INSERT INTO shadow_items VALUES (1, 'original', 50)");

        $rawSkipped = $rawPdo->query("INSERT INTO native_items VALUES (1, 'skipped', 95) ON CONFLICT(id) DO UPDATE SET name = excluded.name WHERE native_items.score >= 80 RETURNING id, name, score");
        $ztdSkipped = $ztdPdo->query("INSERT INTO shadow_items VALUES (1, 'skipped', 95) ON CONFLICT(id) DO UPDATE SET name = excluded.name WHERE shadow_items.score >= 80 RETURNING id, name, score");
        self::assertNotFalse($rawSkipped);
        self::assertNotFalse($ztdSkipped);
        self::assertSame($rawSkipped->fetchAll(), $ztdSkipped->fetchAll());

        $rawPdo->exec('UPDATE native_items SET score = 85 WHERE id = 1');
        $ztdPdo->exec('UPDATE shadow_items SET score = 85 WHERE id = 1');
        $rawUpdated = $rawPdo->query("INSERT INTO native_items VALUES (1, 'updated', 95) ON CONFLICT(id) DO UPDATE SET name = excluded.name WHERE native_items.score >= 80 RETURNING id, name, score");
        $ztdUpdated = $ztdPdo->query("INSERT INTO shadow_items VALUES (1, 'updated', 95) ON CONFLICT(id) DO UPDATE SET name = excluded.name WHERE shadow_items.score >= 80 RETURNING id, name, score");
        self::assertNotFalse($rawUpdated);
        self::assertNotFalse($ztdUpdated);
        self::assertSame($rawUpdated->fetchAll(), $ztdUpdated->fetchAll());
    }
}
