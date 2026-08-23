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
final class UpsertRowAliasTest extends TestCase
{
    public function testRowAliasResolvesIncomingValuesForLiteralAndPreparedUpserts(): void
    {
        [$databaseName, $rawPdo] = MySqlContainer::createTestDatabase();
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
