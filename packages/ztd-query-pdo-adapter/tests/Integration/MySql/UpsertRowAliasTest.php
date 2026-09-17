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
final class UpsertRowAliasTest extends TestCase
{
    public function testRowAliasResolvesIncomingValuesForLiteralAndPreparedUpserts(): void
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
            $rawPdo->exec("CREATE TABLE `{$table}` (id INT PRIMARY KEY, name VARCHAR(50), score INT)");
            $ztdPdo = ZtdPdo::fromPdo($rawPdo);
            $ztdPdo->exec("INSERT INTO `{$table}` VALUES (1, 'original', 10)");

            self::assertSame(1, $ztdPdo->exec("INSERT INTO `{$table}` VALUES (1, 'literal', 20) AS incoming ON DUPLICATE KEY UPDATE name = incoming.name, score = incoming.score"));

            $statement = $ztdPdo->prepare("INSERT INTO `{$table}` VALUES (?, ?, ?) AS incoming ON DUPLICATE KEY UPDATE name = incoming.name, score = incoming.score");
            self::assertNotFalse($statement);
            self::assertTrue($statement->execute([1, 'prepared', 30]));
            self::assertSame(1, $statement->rowCount());

            $row = $ztdPdo->query("SELECT id, name, score FROM `{$table}`");
            self::assertNotFalse($row);
            self::assertSame(['id' => 1, 'name' => 'prepared', 'score' => 30], $row->fetch(PDO::FETCH_ASSOC));

            $physical = $rawPdo->query("SELECT * FROM `{$table}`");
            self::assertNotFalse($physical);
            self::assertSame([], $physical->fetchAll(PDO::FETCH_ASSOC));
        } finally {
            $rawPdo->exec(sprintf('DROP DATABASE IF EXISTS `%s`', $databaseName));
        }
    }
}
