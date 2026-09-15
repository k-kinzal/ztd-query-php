<?php

declare(strict_types=1);

namespace Tests\Integration\MySql;

use PDO;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;
use Tests\Container\MySqlContainer;
use ZtdQuery\Adapter\Pdo\ZtdPdo;

/**
 * @requires extension pdo_mysql
 * @group integration
 * @group mysql
 */
#[CoversNothing]
#[Large]
final class PartitionSelectionTest extends TestCase
{
    public function testRangePartitionSelectionMatchesShadowRows(): void
    {
        $containerInstance = \Testcontainers\Testcontainers::run(MySqlContainer::class);
        /** @var PDO $pdo */
        $pdo = $containerInstance->getData(PDO::class);

        $databaseName = 'ztd_' . bin2hex(random_bytes(8));
        $pdo->exec(sprintf('CREATE DATABASE `%s` CHARACTER SET utf8mb4', $databaseName));
        $pdo->exec(sprintf('USE `%s`', $databaseName));


        try {
            $pdo->exec('CREATE TABLE events (id INT NOT NULL, event_date DATE NOT NULL, '
                . 'PRIMARY KEY (id, event_date)) PARTITION BY RANGE (YEAR(event_date)) ('
                . 'PARTITION p2023 VALUES LESS THAN (2024), '
                . 'PARTITION p2024 VALUES LESS THAN (2025), '
                . 'PARTITION pmax VALUES LESS THAN MAXVALUE)');
            $ztdPdo = ZtdPdo::fromPdo($pdo);
            self::assertSame(4, $ztdPdo->exec('INSERT INTO events VALUES '
                . "(1, '2023-06-01'), (2, '2024-01-15'), (3, '2024-11-20'), (4, '2025-02-01')"));

            $selected = $ztdPdo->query('SELECT id FROM events PARTITION (p2024) ORDER BY id');
            self::assertNotFalse($selected);
            self::assertSame([2, 3], $selected->fetchAll(PDO::FETCH_COLUMN));

            $combined = $ztdPdo->query('SELECT e.id FROM events PARTITION (p2023, pmax) AS e ORDER BY e.id');
            self::assertNotFalse($combined);
            self::assertSame([1, 4], $combined->fetchAll(PDO::FETCH_COLUMN));

            $physical = $pdo->query('SELECT COUNT(*) FROM events');
            self::assertNotFalse($physical);
            self::assertSame(0, (int) $physical->fetchColumn());
        } finally {
            $pdo->exec(sprintf('DROP DATABASE IF EXISTS `%s`', $databaseName));
        }
    }
}
