<?php

declare(strict_types=1);

namespace Tests\Integration\PostgreSql;

use Container\Endpoint;
use Container\PostgreSql16Container;
use PDO;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Adapter\Pdo\ZtdPdo;

/**
 * @requires extension pdo_pgsql
 * @group integration
 * @group postgres
 */
#[CoversNothing]
#[Large]
final class PartitionChildTest extends TestCase
{
    public function testParentAndChildDmlSharePartitionedShadowRows(): void
    {
        $endpoint = \Testcontainers\Testcontainers::run(PostgreSql16Container::class)->getData(Endpoint::class);
        /** @var PDO $pdo */
        $pdo = new PDO(
            $endpoint->dsn(),
            $endpoint->username,
            $endpoint->password,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC],
        );

        $schemaName = 'ztd_' . bin2hex(random_bytes(8));
        $pdo->exec(sprintf('CREATE SCHEMA "%s"', $schemaName));
        $pdo->exec(sprintf('SET search_path TO "%s"', $schemaName));


        try {
            $pdo->exec('CREATE TABLE logs (id INTEGER NOT NULL, log_date DATE NOT NULL, level TEXT NOT NULL, '
                . 'PRIMARY KEY (id, log_date)) PARTITION BY RANGE (log_date)');
            $pdo->exec("CREATE TABLE logs_2024 PARTITION OF logs FOR VALUES FROM ('2024-01-01') TO ('2025-01-01')");
            $pdo->exec("CREATE TABLE logs_2025 PARTITION OF logs FOR VALUES FROM ('2025-01-01') TO ('2026-01-01')");
            $ztdPdo = ZtdPdo::fromPdo($pdo);

            self::assertSame(2, $ztdPdo->exec(
                "INSERT INTO logs VALUES (1, '2024-05-01', 'INFO'), (2, '2025-05-01', 'WARN')",
            ));

            $parent = $ztdPdo->query('SELECT id FROM logs ORDER BY id');
            self::assertNotFalse($parent);
            self::assertSame([1, 2], $parent->fetchAll(PDO::FETCH_COLUMN));
            $child2024 = $ztdPdo->query('SELECT id FROM logs_2024 ORDER BY id');
            self::assertNotFalse($child2024);
            self::assertSame([1], $child2024->fetchAll(PDO::FETCH_COLUMN));
            $child2025 = $ztdPdo->query('SELECT id FROM logs_2025 ORDER BY id');
            self::assertNotFalse($child2025);
            self::assertSame([2], $child2025->fetchAll(PDO::FETCH_COLUMN));

            self::assertSame(1, $ztdPdo->exec("INSERT INTO logs_2024 VALUES (3, '2024-09-01', 'DEBUG')"));
            self::assertSame(1, $ztdPdo->exec("UPDATE logs_2024 SET level = 'NOTICE' WHERE id = 1"));
            self::assertSame(1, $ztdPdo->exec('DELETE FROM logs_2025 WHERE id = 2'));

            $updated = $ztdPdo->query('SELECT id, level FROM logs ORDER BY id');
            self::assertNotFalse($updated);
            self::assertSame(
                [['id' => 1, 'level' => 'NOTICE'], ['id' => 3, 'level' => 'DEBUG']],
                $updated->fetchAll(),
            );
            $physical = $pdo->query('SELECT COUNT(*) FROM logs');
            self::assertNotFalse($physical);
            self::assertSame(0, (int) $physical->fetchColumn());
        } finally {
            $pdo->exec(sprintf('DROP SCHEMA IF EXISTS "%s" CASCADE', $schemaName));
        }
    }
}
