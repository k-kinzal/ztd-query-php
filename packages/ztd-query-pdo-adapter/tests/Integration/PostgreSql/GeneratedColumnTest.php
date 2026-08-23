<?php

declare(strict_types=1);

namespace Tests\Integration\PostgreSql;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;
use Tests\Fixtures\PostgreSqlContainer;
use ZtdQuery\Adapter\Pdo\ZtdPdo;

/**
 * @requires extension pdo_pgsql
 * @group integration
 * @group postgres
 */
#[CoversNothing]
#[Large]
final class GeneratedColumnTest extends TestCase
{
    public function testGeneratedValuesDriveReadsAggregatesUpdatesAndDeletes(): void
    {
        [$schemaName, $pdo] = PostgreSqlContainer::createTestSchema();

        try {
            $pdo->exec('CREATE TABLE orders (id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY, qty INTEGER NOT NULL, unit_price NUMERIC(10,2) NOT NULL, total NUMERIC(12,2) GENERATED ALWAYS AS (qty * unit_price) STORED)');
            $ztdPdo = ZtdPdo::fromPdo($pdo);

            $ztdPdo->exec('INSERT INTO orders (qty, unit_price) VALUES (5, 10), (2, 7)');

            $rows = $ztdPdo->query('SELECT id::integer AS id, total::integer AS total FROM orders ORDER BY id');
            self::assertNotFalse($rows);
            self::assertSame(
                [['id' => 1, 'total' => 50], ['id' => 2, 'total' => 14]],
                $rows->fetchAll(),
            );
            $sum = $ztdPdo->query('SELECT SUM(total)::integer FROM orders');
            self::assertNotFalse($sum);
            self::assertSame(64, $sum->fetchColumn());
            $filtered = $ztdPdo->query('SELECT id::integer FROM orders WHERE total > 20');
            self::assertNotFalse($filtered);
            self::assertSame([1], $filtered->fetchAll(\PDO::FETCH_COLUMN));

            self::assertSame(1, $ztdPdo->exec('UPDATE orders SET qty = 7 WHERE id = 1'));
            $updated = $ztdPdo->query('SELECT total::integer FROM orders WHERE id = 1');
            self::assertNotFalse($updated);
            self::assertSame(70, $updated->fetchColumn());
            self::assertSame(1, $ztdPdo->exec('DELETE FROM orders WHERE total >= 70'));
            $remaining = $ztdPdo->query('SELECT id::integer FROM orders');
            self::assertNotFalse($remaining);
            self::assertSame([2], $remaining->fetchAll(\PDO::FETCH_COLUMN));
        } finally {
            $pdo->exec(sprintf('DROP SCHEMA IF EXISTS "%s" CASCADE', $schemaName));
        }
    }
}
