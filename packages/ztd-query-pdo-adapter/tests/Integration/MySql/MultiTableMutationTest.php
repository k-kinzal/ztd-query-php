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

#[CoversNothing]
#[Large]
final class MultiTableMutationTest extends TestCase
{
    public function testUpdatesAndDeletesEveryListedTable(): void
    {
        $containerInstance = \Testcontainers\Testcontainers::run(getenv('MYSQL_VERSION') === '8.4.7' ? MySql84Container::class : MySql80Container::class);
        /** @var PDO $rawPdo */
        $rawPdo = new PDO(
            sprintf('mysql:host=%s;port=%d;dbname=test;charset=utf8mb4', str_replace('localhost', '127.0.0.1', $containerInstance->getHost()), $containerInstance->getMappedPort(3306)),
            'root',
            'root',
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC],
        );

        $databaseName = 'ztd_' . bin2hex(random_bytes(8));
        $rawPdo->exec(sprintf('CREATE DATABASE `%s` CHARACTER SET utf8mb4', $databaseName));
        $rawPdo->exec(sprintf('USE `%s`', $databaseName));

        $users = 'users_' . bin2hex(random_bytes(8));
        $orders = 'orders_' . bin2hex(random_bytes(8));

        try {
            $rawPdo->exec("CREATE TABLE `{$users}` (id INT PRIMARY KEY, name VARCHAR(50))");
            $rawPdo->exec("CREATE TABLE `{$orders}` (order_id INT PRIMARY KEY, user_id INT, status VARCHAR(50))");
            $ztdPdo = ZtdPdo::fromPdo($rawPdo);
            $ztdPdo->exec("INSERT INTO `{$users}` VALUES (1, 'Alice'), (2, 'Bob')");
            $ztdPdo->exec("INSERT INTO `{$orders}` VALUES (10, 1, 'pending'), (20, 2, 'pending')");

            $ztdPdo->exec(
                "UPDATE `{$users}` u, `{$orders}` o SET u.name = 'Updated', o.status = 'done'"
                . ' WHERE u.id = o.user_id AND u.id = 2',
            );

            $userRows = $ztdPdo->query("SELECT id, name FROM `{$users}` ORDER BY id");
            $orderRows = $ztdPdo->query("SELECT order_id, user_id, status FROM `{$orders}` ORDER BY order_id");
            self::assertNotFalse($userRows);
            self::assertNotFalse($orderRows);
            self::assertSame(
                [['id' => 1, 'name' => 'Alice'], ['id' => 2, 'name' => 'Updated']],
                $userRows->fetchAll(PDO::FETCH_ASSOC),
            );
            self::assertSame(
                [
                    ['order_id' => 10, 'user_id' => 1, 'status' => 'pending'],
                    ['order_id' => 20, 'user_id' => 2, 'status' => 'done'],
                ],
                $orderRows->fetchAll(PDO::FETCH_ASSOC),
            );

            $ztdPdo->exec(
                "DELETE u, o FROM `{$users}` u JOIN `{$orders}` o ON u.id = o.user_id WHERE u.id = 2",
            );

            $remainingUsers = $ztdPdo->query("SELECT id FROM `{$users}` ORDER BY id");
            $remainingOrders = $ztdPdo->query("SELECT order_id FROM `{$orders}` ORDER BY order_id");
            self::assertNotFalse($remainingUsers);
            self::assertNotFalse($remainingOrders);
            self::assertSame([1], $remainingUsers->fetchAll(PDO::FETCH_COLUMN));
            self::assertSame([10], $remainingOrders->fetchAll(PDO::FETCH_COLUMN));

            $physicalUsers = $rawPdo->query("SELECT * FROM `{$users}`");
            $physicalOrders = $rawPdo->query("SELECT * FROM `{$orders}`");
            self::assertNotFalse($physicalUsers);
            self::assertNotFalse($physicalOrders);
            self::assertSame([], $physicalUsers->fetchAll());
            self::assertSame([], $physicalOrders->fetchAll());
        } finally {
            $rawPdo->exec(sprintf('DROP DATABASE IF EXISTS `%s`', $databaseName));
        }
    }
}
