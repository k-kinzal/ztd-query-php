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
final class ForeignKeySchemaTest extends TestCase
{
    public function testInsertWithoutColumnListIgnoresNamedForeignKeyConstraint(): void
    {
        [$databaseName, $rawPdo] = MySqlContainer::createTestDatabase();
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
