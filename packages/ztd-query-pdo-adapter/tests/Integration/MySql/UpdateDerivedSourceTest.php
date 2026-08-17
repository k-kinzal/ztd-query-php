<?php

declare(strict_types=1);

namespace Tests\Integration\MySql;

use PDO;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;
use Tests\Fixtures\MySqlContainer;
use ZtdQuery\Adapter\Pdo\ZtdPdo;

#[CoversNothing]
#[Large]
final class UpdateDerivedSourceTest extends TestCase
{
    public function testUpdateJoinPreservesDerivedAggregateSource(): void
    {
        [$databaseName, $rawPdo] = MySqlContainer::createTestDatabase();
        $products = 'prefix_' . bin2hex(random_bytes(8));
        $summary = 'prefix_' . bin2hex(random_bytes(8));

        try {
            $rawPdo->exec("CREATE TABLE `{$products}` (id INT PRIMARY KEY, category VARCHAR(50), price DECIMAL(10,2))");
            $rawPdo->exec("CREATE TABLE `{$summary}` (id INT PRIMARY KEY, category VARCHAR(50), min_price DECIMAL(10,2), item_count INT)");
            $ztdPdo = ZtdPdo::fromPdo($rawPdo);
            $ztdPdo->exec("INSERT INTO `{$products}` VALUES (1, 'electronics', 99.99), (2, 'electronics', 199.99), (3, 'books', 20.00)");
            $ztdPdo->exec("INSERT INTO `{$summary}` VALUES (1, 'electronics', NULL, NULL), (2, 'books', NULL, NULL)");

            self::assertSame(2, $ztdPdo->exec("UPDATE `{$summary}` AS s JOIN (SELECT category, COUNT(*) AS cnt, MIN(price) AS mn FROM `{$products}` GROUP BY category) AS p ON s.category = p.category SET s.min_price = p.mn, s.item_count = p.cnt"));

            $statement = $ztdPdo->query("SELECT id, category, min_price, item_count FROM `{$summary}` ORDER BY id");
            self::assertNotFalse($statement);
            self::assertSame([
                ['id' => 1, 'category' => 'electronics', 'min_price' => '99.99', 'item_count' => 2],
                ['id' => 2, 'category' => 'books', 'min_price' => '20.00', 'item_count' => 1],
            ], $statement->fetchAll(PDO::FETCH_ASSOC));

            $physical = $rawPdo->query("SELECT * FROM `{$summary}`");
            self::assertNotFalse($physical);
            self::assertSame([], $physical->fetchAll(PDO::FETCH_ASSOC));
        } finally {
            $rawPdo->exec(sprintf('DROP DATABASE IF EXISTS `%s`', $databaseName));
        }
    }

    public function testUpdateJoinPreservesWindowedDerivedSource(): void
    {
        [$databaseName, $rawPdo] = MySqlContainer::createTestDatabase();
        $scores = 'prefix_' . bin2hex(random_bytes(8));
        $rankings = 'prefix_' . bin2hex(random_bytes(8));

        try {
            $rawPdo->exec("CREATE TABLE `{$scores}` (id INT PRIMARY KEY, player VARCHAR(30), score INT)");
            $rawPdo->exec("CREATE TABLE `{$rankings}` (id INT PRIMARY KEY, player VARCHAR(30), rank_pos INT)");
            $ztdPdo = ZtdPdo::fromPdo($rawPdo);
            $ztdPdo->exec("INSERT INTO `{$scores}` VALUES (1, 'Alice', 100), (2, 'Alice', 150), (3, 'Bob', 200)");
            $ztdPdo->exec("INSERT INTO `{$rankings}` VALUES (1, 'Alice', NULL), (2, 'Bob', NULL)");

            self::assertSame(2, $ztdPdo->exec("UPDATE `{$rankings}` AS r JOIN (SELECT player, DENSE_RANK() OVER (ORDER BY MAX(score) DESC) AS ranking FROM `{$scores}` GROUP BY player) AS s ON r.player = s.player SET r.rank_pos = s.ranking"));

            $statement = $ztdPdo->query("SELECT player, rank_pos FROM `{$rankings}` ORDER BY player");
            self::assertNotFalse($statement);
            self::assertSame([
                ['player' => 'Alice', 'rank_pos' => 2],
                ['player' => 'Bob', 'rank_pos' => 1],
            ], $statement->fetchAll(PDO::FETCH_ASSOC));

            $physical = $rawPdo->query("SELECT * FROM `{$rankings}`");
            self::assertNotFalse($physical);
            self::assertSame([], $physical->fetchAll(PDO::FETCH_ASSOC));
        } finally {
            $rawPdo->exec(sprintf('DROP DATABASE IF EXISTS `%s`', $databaseName));
        }
    }
}
