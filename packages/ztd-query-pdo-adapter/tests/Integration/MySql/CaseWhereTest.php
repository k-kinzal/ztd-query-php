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
final class CaseWhereTest extends TestCase
{
    public function testUpdateAndDeleteRestrictRowsWithCaseExpression(): void
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

        $updates = 'prefix_' . bin2hex(random_bytes(8));
        $deletes = 'prefix_' . bin2hex(random_bytes(8));

        try {
            $rawPdo->exec("CREATE TABLE `{$updates}` (id INT PRIMARY KEY, score INT)");
            $rawPdo->exec("CREATE TABLE `{$deletes}` (id INT PRIMARY KEY, score INT)");
            $ztdPdo = ZtdPdo::fromPdo($rawPdo);
            $rows = 'VALUES (1, 85), (2, 60), (3, 95), (4, 45)';
            $ztdPdo->exec("INSERT INTO `{$updates}` {$rows}");
            $ztdPdo->exec("INSERT INTO `{$deletes}` {$rows}");

            self::assertSame(2, $ztdPdo->exec("UPDATE `{$updates}` SET score = 0 WHERE CASE WHEN score > 80 THEN 1 ELSE 0 END = 1"));
            self::assertSame(2, $ztdPdo->exec("DELETE FROM `{$deletes}` WHERE CASE WHEN score > 80 THEN 1 ELSE 0 END = 1"));

            $updated = $ztdPdo->query("SELECT id, score FROM `{$updates}` ORDER BY id");
            $remaining = $ztdPdo->query("SELECT id, score FROM `{$deletes}` ORDER BY id");
            self::assertNotFalse($updated);
            self::assertNotFalse($remaining);
            self::assertSame([
                ['id' => 1, 'score' => 0],
                ['id' => 2, 'score' => 60],
                ['id' => 3, 'score' => 0],
                ['id' => 4, 'score' => 45],
            ], $updated->fetchAll(PDO::FETCH_ASSOC));
            self::assertSame([
                ['id' => 2, 'score' => 60],
                ['id' => 4, 'score' => 45],
            ], $remaining->fetchAll(PDO::FETCH_ASSOC));

            $physicalUpdates = $rawPdo->query("SELECT * FROM `{$updates}`");
            $physicalDeletes = $rawPdo->query("SELECT * FROM `{$deletes}`");
            self::assertNotFalse($physicalUpdates);
            self::assertNotFalse($physicalDeletes);
            self::assertSame([], $physicalUpdates->fetchAll(PDO::FETCH_ASSOC));
            self::assertSame([], $physicalDeletes->fetchAll(PDO::FETCH_ASSOC));
        } finally {
            $rawPdo->exec(sprintf('DROP DATABASE IF EXISTS `%s`', $databaseName));
        }
    }
}
