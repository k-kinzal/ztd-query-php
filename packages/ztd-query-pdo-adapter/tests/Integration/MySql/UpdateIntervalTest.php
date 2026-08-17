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
final class UpdateIntervalTest extends TestCase
{
    public function testUpdatePreservesIntervalUnit(): void
    {
        [$databaseName, $rawPdo] = MySqlContainer::createTestDatabase();
        $table = 'prefix_' . bin2hex(random_bytes(8));

        try {
            $rawPdo->exec("CREATE TABLE `{$table}` (id INT PRIMARY KEY, created_at DATETIME NOT NULL, due_at DATETIME)");
            $ztdPdo = ZtdPdo::fromPdo($rawPdo);
            $ztdPdo->exec("INSERT INTO `{$table}` VALUES (1, '2025-01-01 10:00:00', NULL)");

            self::assertSame(1, $ztdPdo->exec("UPDATE `{$table}` SET due_at = created_at + INTERVAL 30 DAY WHERE id = 1"));

            $statement = $ztdPdo->query("SELECT due_at FROM `{$table}` WHERE id = 1");
            self::assertNotFalse($statement);
            self::assertSame('2025-01-31 10:00:00', $statement->fetchColumn());

            $physical = $rawPdo->query("SELECT due_at FROM `{$table}`");
            self::assertNotFalse($physical);
            self::assertSame([], $physical->fetchAll(PDO::FETCH_COLUMN));
        } finally {
            $rawPdo->exec(sprintf('DROP DATABASE IF EXISTS `%s`', $databaseName));
        }
    }
}
