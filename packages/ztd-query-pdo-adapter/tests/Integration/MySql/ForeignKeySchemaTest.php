<?php

declare(strict_types=1);

namespace Tests\Integration\MySql;

use Container\Endpoint;
use Container\MySql80Container;
use Container\MySql84Container;
use PDO;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Adapter\Pdo\ZtdPdo;

#[CoversNothing]
#[Large]
final class ForeignKeySchemaTest extends TestCase
{
    public function testInsertWithoutColumnListIgnoresNamedForeignKeyConstraint(): void
    {
        $endpoint = \Testcontainers\Testcontainers::run(getenv('MYSQL_VERSION') === '8.4.7' ? MySql84Container::class : MySql80Container::class)->getData(Endpoint::class);
        /** @var PDO $rawPdo */
        $rawPdo = new PDO(
            $endpoint->dsn(),
            $endpoint->username,
            $endpoint->password,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC],
        );

        $databaseName = 'ztd_' . bin2hex(random_bytes(8));
        $rawPdo->exec(sprintf('CREATE DATABASE `%s` CHARACTER SET utf8mb4', $databaseName));
        $rawPdo->exec(sprintf('USE `%s`', $databaseName));

        $parent = 'prefix_' . bin2hex(random_bytes(8));
        $child = 'prefix_' . bin2hex(random_bytes(8));

        try {
            $rawPdo->exec("CREATE TABLE `{$parent}` (id INT PRIMARY KEY, name VARCHAR(50)) ENGINE=InnoDB");
            $rawPdo->exec("CREATE TABLE `{$child}` (id INT PRIMARY KEY, parent_id INT NOT NULL, label VARCHAR(50) NOT NULL, CONSTRAINT `fk_parent` FOREIGN KEY (parent_id) REFERENCES `{$parent}`(id)) ENGINE=InnoDB");
            $ztdPdo = ZtdPdo::fromPdo($rawPdo);
            $ztdPdo->exec("INSERT INTO `{$parent}` VALUES (1, 'Parent')");

            self::assertSame(1, $ztdPdo->exec("INSERT INTO `{$child}` VALUES (10, 1, 'Child')"));

            $statement = $ztdPdo->query("SELECT * FROM `{$child}`");
            self::assertNotFalse($statement);
            self::assertSame([['id' => 10, 'parent_id' => 1, 'label' => 'Child']], $statement->fetchAll(PDO::FETCH_ASSOC));

            $physical = $rawPdo->query("SELECT * FROM `{$child}`");
            self::assertNotFalse($physical);
            self::assertSame([], $physical->fetchAll(PDO::FETCH_ASSOC));
        } finally {
            $rawPdo->exec(sprintf('DROP DATABASE IF EXISTS `%s`', $databaseName));
        }
    }
}
