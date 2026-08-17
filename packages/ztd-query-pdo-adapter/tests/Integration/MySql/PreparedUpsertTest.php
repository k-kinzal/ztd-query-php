<?php

declare(strict_types=1);

namespace Tests\Integration\MySql;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;
use Tests\Fixtures\MySqlContainer;
use ZtdQuery\Adapter\Pdo\ZtdPdo;

#[CoversNothing]
#[Large]
final class PreparedUpsertTest extends TestCase
{
    public function testPreparedReplaceRemovesExistingPrimaryKey(): void
    {
        [$databaseName, $rawPdo] = MySqlContainer::createTestDatabase();
        $table = 'prefix_' . bin2hex(random_bytes(8));

        try {
            $rawPdo->exec("CREATE TABLE `{$table}` (id INT PRIMARY KEY, name VARCHAR(50), value INT)");
            $ztdPdo = ZtdPdo::fromPdo($rawPdo);
            $ztdPdo->exec("INSERT INTO `{$table}` VALUES (1, 'original', 100)");
            $statement = $ztdPdo->prepare("REPLACE INTO `{$table}` VALUES (?, ?, ?)");
            self::assertNotFalse($statement);

            self::assertTrue($statement->execute(['1', 'replaced', 999]));

            $rows = $ztdPdo->query("SELECT * FROM `{$table}` WHERE id = 1");
            self::assertNotFalse($rows);
            self::assertSame([['id' => 1, 'name' => 'replaced', 'value' => 999]], $rows->fetchAll());
        } finally {
            $rawPdo->exec(sprintf('DROP DATABASE IF EXISTS `%s`', $databaseName));
        }
    }

    public function testPreparedOnDuplicateKeyUpdateReplacesExistingValues(): void
    {
        [$databaseName, $rawPdo] = MySqlContainer::createTestDatabase();
        $table = 'prefix_' . bin2hex(random_bytes(8));

        try {
            $rawPdo->exec("CREATE TABLE `{$table}` (id INT PRIMARY KEY, name VARCHAR(50))");
            $ztdPdo = ZtdPdo::fromPdo($rawPdo);
            $ztdPdo->exec("INSERT INTO `{$table}` VALUES (1, 'original')");
            $statement = $ztdPdo->prepare(
                "INSERT INTO `{$table}` VALUES (?, ?) ON DUPLICATE KEY UPDATE name = VALUES(name)"
            );
            self::assertNotFalse($statement);

            self::assertTrue($statement->execute(['1', 'updated']));

            $rows = $ztdPdo->query("SELECT * FROM `{$table}` WHERE id = 1");
            self::assertNotFalse($rows);
            self::assertSame([['id' => 1, 'name' => 'updated']], $rows->fetchAll());
        } finally {
            $rawPdo->exec(sprintf('DROP DATABASE IF EXISTS `%s`', $databaseName));
        }
    }
}
