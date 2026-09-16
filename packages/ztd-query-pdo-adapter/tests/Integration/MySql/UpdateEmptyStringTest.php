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
final class UpdateEmptyStringTest extends TestCase
{
    public function testUpdateReplacesExistingTextWithEmptyString(): void
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

        $table = 'prefix_' . bin2hex(random_bytes(8));

        try {
            $rawPdo->exec("CREATE TABLE `{$table}` (id INT PRIMARY KEY, name VARCHAR(100), notes TEXT)");
            $ztdPdo = ZtdPdo::fromPdo($rawPdo);
            $ztdPdo->exec("INSERT INTO `{$table}` VALUES (1, 'Alice', 'some notes')");

            self::assertSame(1, $ztdPdo->exec("UPDATE `{$table}` SET notes = '' WHERE name = 'Alice'"));

            $statement = $ztdPdo->query("SELECT notes FROM `{$table}` WHERE id = 1");
            self::assertNotFalse($statement);
            self::assertSame('', $statement->fetchColumn());

            $physical = $rawPdo->query("SELECT notes FROM `{$table}`");
            self::assertNotFalse($physical);
            self::assertSame([], $physical->fetchAll(PDO::FETCH_COLUMN));
        } finally {
            $rawPdo->exec(sprintf('DROP DATABASE IF EXISTS `%s`', $databaseName));
        }
    }
}
