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
 * @group integration
 * @group sqlite
 */
#[CoversNothing]
#[Large]
final class MultiStatementExecTest extends TestCase
{
    public function testExecProcessesStatementsAgainstEachPrecedingShadowState(): void
    {
        $rawPdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $rawPdo->exec('CREATE TABLE items (id INTEGER PRIMARY KEY, name TEXT NOT NULL)');
        $ztdPdo = ZtdPdo::fromPdo($rawPdo);

        self::assertSame(2, $ztdPdo->exec(
            "INSERT INTO items VALUES (1, 'a'); INSERT INTO items VALUES (2, 'b'), (3, 'c')",
        ));
        self::assertSame(1, $ztdPdo->exec(
            "INSERT INTO items VALUES (4, 'before'); UPDATE items SET name = 'after' WHERE id = 4",
        ));
        self::assertSame(2, $ztdPdo->exec(
            'DELETE FROM items WHERE id = 1; DELETE FROM items WHERE id IN (2, 3)',
        ));
        self::assertSame(1, $ztdPdo->exec(
            "UPDATE items SET name = 'semi;colon' WHERE id = 4; -- not a ; statement\n"
            . "UPDATE items SET name = name || ';done' WHERE id = 4",
        ));

        $shadow = $ztdPdo->query('SELECT id, name FROM items');
        self::assertNotFalse($shadow);
        self::assertSame([['id' => 4, 'name' => 'semi;colon;done']], $shadow->fetchAll(PDO::FETCH_ASSOC));
        $physical = $rawPdo->query('SELECT COUNT(*) FROM items');
        self::assertNotFalse($physical);
        self::assertSame(0, (int) $physical->fetchColumn());
    }

    public function testTransactionStatementsInBatchControlShadowRollback(): void
    {
        $rawPdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $rawPdo->exec('CREATE TABLE items (id INTEGER PRIMARY KEY, name TEXT NOT NULL)');
        $ztdPdo = ZtdPdo::fromPdo($rawPdo);

        self::assertSame(0, $ztdPdo->exec(
            "BEGIN; INSERT INTO items VALUES (1, 'rolled back'); ROLLBACK",
        ));

        $shadow = $ztdPdo->query('SELECT COUNT(*) FROM items');
        self::assertNotFalse($shadow);
        self::assertSame(0, (int) $shadow->fetchColumn());
    }
}
