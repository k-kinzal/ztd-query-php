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
final class UpdateHexLiteralTest extends TestCase
{
    public function testUpdatePreservesIntroducedHexLiteral(): void
    {
        [$databaseName, $rawPdo] = MySqlContainer::createTestDatabase();
        $table = 'prefix_' . bin2hex(random_bytes(8));

        try {
            $rawPdo->exec("CREATE TABLE `{$table}` (id INT PRIMARY KEY, payload VARBINARY(255))");
            $ztdPdo = ZtdPdo::fromPdo($rawPdo);
            $ztdPdo->exec("INSERT INTO `{$table}` VALUES (1, X'48656C6C6F')");

            self::assertSame(1, $ztdPdo->exec("UPDATE `{$table}` SET payload = X'576F726C64' WHERE id = 1"));

            $statement = $ztdPdo->query("SELECT payload FROM `{$table}` WHERE id = 1");
            self::assertNotFalse($statement);
            self::assertSame('World', $statement->fetchColumn());

            $physical = $rawPdo->query("SELECT payload FROM `{$table}`");
            self::assertNotFalse($physical);
            self::assertSame([], $physical->fetchAll(PDO::FETCH_COLUMN));
        } finally {
            $rawPdo->exec(sprintf('DROP DATABASE IF EXISTS `%s`', $databaseName));
        }
    }
}
