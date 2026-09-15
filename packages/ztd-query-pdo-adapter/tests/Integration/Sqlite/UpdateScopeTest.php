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
final class UpdateScopeTest extends TestCase
{
    public function testUpdateFromReadsJoinedShadowRows(): void
    {
        $rawPdo = new PDO('sqlite::memory:', null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $rawPdo->exec('CREATE TABLE native_inventory (id INTEGER PRIMARY KEY, quantity INTEGER)');
        $rawPdo->exec('CREATE TABLE native_incoming (id INTEGER PRIMARY KEY, delta INTEGER)');
        $rawPdo->exec('CREATE TABLE shadow_inventory (id INTEGER PRIMARY KEY, quantity INTEGER)');
        $rawPdo->exec('CREATE TABLE shadow_incoming (id INTEGER PRIMARY KEY, delta INTEGER)');
        $ztdPdo = ZtdPdo::fromPdo($rawPdo);
        $rawPdo->exec('INSERT INTO native_inventory VALUES (1, 10), (2, 20)');
        $rawPdo->exec('INSERT INTO native_incoming VALUES (1, 5), (2, -3)');
        $ztdPdo->exec('INSERT INTO shadow_inventory VALUES (1, 10), (2, 20)');
        $ztdPdo->exec('INSERT INTO shadow_incoming VALUES (1, 5), (2, -3)');

        $rawPdo->exec('UPDATE native_inventory SET quantity = quantity + native_incoming.delta FROM native_incoming WHERE native_inventory.id = native_incoming.id');
        $ztdPdo->exec('UPDATE shadow_inventory SET quantity = quantity + shadow_incoming.delta FROM shadow_incoming WHERE shadow_inventory.id = shadow_incoming.id');
        $native = $rawPdo->query('SELECT * FROM native_inventory ORDER BY id');
        $shadow = $ztdPdo->query('SELECT * FROM shadow_inventory ORDER BY id');

        self::assertNotFalse($native);
        self::assertNotFalse($shadow);
        self::assertSame($native->fetchAll(), $shadow->fetchAll());
    }

    public function testUpdateTargetAliasQualifiesSelectionScope(): void
    {
        $rawPdo = new PDO('sqlite::memory:', null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $rawPdo->exec('CREATE TABLE native_items (id INTEGER PRIMARY KEY, quantity INTEGER)');
        $rawPdo->exec('CREATE TABLE shadow_items (id INTEGER PRIMARY KEY, quantity INTEGER)');
        $ztdPdo = ZtdPdo::fromPdo($rawPdo);
        $rawPdo->exec('INSERT INTO native_items VALUES (1, 10), (2, 20)');
        $ztdPdo->exec('INSERT INTO shadow_items VALUES (1, 10), (2, 20)');

        $rawPdo->exec('UPDATE native_items AS target SET quantity = quantity + 1 WHERE target.id = 1');
        $ztdPdo->exec('UPDATE shadow_items AS target SET quantity = quantity + 1 WHERE target.id = 1');
        $native = $rawPdo->query('SELECT * FROM native_items ORDER BY id');
        $shadow = $ztdPdo->query('SELECT * FROM shadow_items ORDER BY id');

        self::assertNotFalse($native);
        self::assertNotFalse($shadow);
        self::assertSame($native->fetchAll(), $shadow->fetchAll());
    }
}
