<?php

declare(strict_types=1);

namespace Tests\Integration\Sqlite;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Adapter\Pdo\ZtdPdo;

/** @requires extension pdo_sqlite */
#[CoversNothing]
#[Large]
final class GeneratedColumnTest extends TestCase
{
    public function testGeneratedValuesDriveReadsAggregatesUpdatesAndDeletes(): void
    {
        $pdo = new \PDO('sqlite::memory:', null, null, [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
        ]);
        $pdo->exec('CREATE TABLE orders (id INTEGER PRIMARY KEY, qty INTEGER NOT NULL, unit_price REAL NOT NULL, total REAL GENERATED ALWAYS AS (qty * unit_price) STORED)');
        $ztdPdo = ZtdPdo::fromPdo($pdo);

        $ztdPdo->exec('INSERT INTO orders (id, qty, unit_price) VALUES (1, 5, 10), (2, 2, 7)');

        $rows = $ztdPdo->query('SELECT id, CAST(total AS INTEGER) AS total FROM orders ORDER BY id');
        self::assertNotFalse($rows);
        self::assertSame(
            [['id' => 1, 'total' => 50], ['id' => 2, 'total' => 14]],
            $rows->fetchAll(),
        );
        $sum = $ztdPdo->query('SELECT CAST(SUM(total) AS INTEGER) FROM orders');
        self::assertNotFalse($sum);
        self::assertSame(64, $sum->fetchColumn());
        $filtered = $ztdPdo->query('SELECT id FROM orders WHERE total > 20');
        self::assertNotFalse($filtered);
        self::assertSame([1], $filtered->fetchAll(\PDO::FETCH_COLUMN));

        self::assertSame(1, $ztdPdo->exec('UPDATE orders SET qty = 7 WHERE id = 1'));
        $updated = $ztdPdo->query('SELECT CAST(total AS INTEGER) FROM orders WHERE id = 1');
        self::assertNotFalse($updated);
        self::assertSame(70, $updated->fetchColumn());
        self::assertSame(1, $ztdPdo->exec('DELETE FROM orders WHERE total >= 70'));
        $remaining = $ztdPdo->query('SELECT id FROM orders');
        self::assertNotFalse($remaining);
        self::assertSame([2], $remaining->fetchAll(\PDO::FETCH_COLUMN));
    }
}
