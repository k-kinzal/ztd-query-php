<?php

declare(strict_types=1);

namespace Tests\Integration\MySql;

use Container\MySql80Container;
use Container\MySql84Container;
use PDO;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Adapter\Pdo\ZtdPdo;

/**
 * @requires extension pdo_mysql
 * @group integration
 * @group mysql
 */
#[CoversNothing]
#[Large]
final class GeneratedColumnTest extends TestCase
{
    public function testGeneratedValuesDriveReadsAggregatesUpdatesAndDeletes(): void
    {
        $containerInstance = \Testcontainers\Testcontainers::run(getenv('MYSQL_VERSION') === '8.4.7' ? MySql84Container::class : MySql80Container::class);
        /** @var PDO $pdo */
        $pdo = new PDO(
            sprintf('mysql:host=%s;port=%d;dbname=test;charset=utf8mb4', str_replace('localhost', '127.0.0.1', $containerInstance->getHost()), $containerInstance->getMappedPort(3306)),
            'root',
            'root',
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC],
        );

        $databaseName = 'ztd_' . bin2hex(random_bytes(8));
        $pdo->exec(sprintf('CREATE DATABASE `%s` CHARACTER SET utf8mb4', $databaseName));
        $pdo->exec(sprintf('USE `%s`', $databaseName));


        try {
            $pdo->exec('CREATE TABLE orders (id BIGINT AUTO_INCREMENT PRIMARY KEY, qty INT NOT NULL, unit_price DECIMAL(10,2) NOT NULL, total DECIMAL(12,2) GENERATED ALWAYS AS (qty * unit_price) STORED)');
            $ztdPdo = ZtdPdo::fromPdo($pdo);

            $ztdPdo->exec('INSERT INTO orders (qty, unit_price) VALUES (5, 10), (2, 7)');

            $rows = $ztdPdo->query('SELECT CAST(id AS SIGNED) AS id, CAST(total AS SIGNED) AS total FROM orders ORDER BY id');
            self::assertNotFalse($rows);
            self::assertSame(
                [['id' => 1, 'total' => 50], ['id' => 2, 'total' => 14]],
                $rows->fetchAll(),
            );
            $sum = $ztdPdo->query('SELECT CAST(SUM(total) AS SIGNED) FROM orders');
            self::assertNotFalse($sum);
            self::assertSame(64, $sum->fetchColumn());
            $filtered = $ztdPdo->query('SELECT CAST(id AS SIGNED) FROM orders WHERE total > 20');
            self::assertNotFalse($filtered);
            self::assertSame([1], $filtered->fetchAll(PDO::FETCH_COLUMN));

            self::assertSame(1, $ztdPdo->exec('UPDATE orders SET qty = 7 WHERE id = 1'));
            $updated = $ztdPdo->query('SELECT CAST(total AS SIGNED) FROM orders WHERE id = 1');
            self::assertNotFalse($updated);
            self::assertSame(70, $updated->fetchColumn());
            self::assertSame(1, $ztdPdo->exec('DELETE FROM orders WHERE total >= 70'));
            $remaining = $ztdPdo->query('SELECT CAST(id AS SIGNED) FROM orders');
            self::assertNotFalse($remaining);
            self::assertSame([2], $remaining->fetchAll(PDO::FETCH_COLUMN));
        } finally {
            $pdo->exec(sprintf('DROP DATABASE IF EXISTS `%s`', $databaseName));
        }
    }
}
