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
final class UpdateEmptyStringTest extends TestCase
{
    public function testUpdateReplacesExistingTextWithEmptyString(): void
    {
        [$databaseName, $rawPdo] = MySqlContainer::createTestDatabase();
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
