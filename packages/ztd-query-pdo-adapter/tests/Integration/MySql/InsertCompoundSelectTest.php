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
final class InsertCompoundSelectTest extends TestCase
{
    public function testInsertPreservesEveryCompoundSelectBranch(): void
    {
        [$databaseName, $rawPdo] = MySqlContainer::createTestDatabase();
        $archive = 'prefix_' . bin2hex(random_bytes(8));
        $current = 'prefix_' . bin2hex(random_bytes(8));
        $combined = 'prefix_' . bin2hex(random_bytes(8));
        $prepared = 'prefix_' . bin2hex(random_bytes(8));

        try {
            $rawPdo->exec("CREATE TABLE `{$archive}` (name VARCHAR(50) PRIMARY KEY, amount DECIMAL(10,2))");
            $rawPdo->exec("CREATE TABLE `{$current}` (name VARCHAR(50) PRIMARY KEY, amount DECIMAL(10,2))");
            $rawPdo->exec("CREATE TABLE `{$combined}` (name VARCHAR(50) PRIMARY KEY, amount DECIMAL(10,2))");
            $rawPdo->exec("CREATE TABLE `{$prepared}` (name VARCHAR(50) PRIMARY KEY, amount DECIMAL(10,2))");
            $ztdPdo = ZtdPdo::fromPdo($rawPdo);
            $ztdPdo->exec("INSERT INTO `{$archive}` VALUES ('Alice', 100.00), ('Bob', 150.00)");
            $ztdPdo->exec("INSERT INTO `{$current}` VALUES ('Carol', 300.00), ('Dave', 50.00)");

            self::assertSame(4, $ztdPdo->exec("INSERT INTO `{$combined}` (name, amount) SELECT name, amount FROM `{$archive}` UNION ALL SELECT name, amount FROM `{$current}`"));

            $statement = $ztdPdo->prepare("INSERT INTO `{$prepared}` (name, amount) SELECT name, amount FROM `{$archive}` WHERE amount >= ? UNION ALL SELECT name, amount FROM `{$current}` WHERE amount >= ?");
            self::assertNotFalse($statement);
            self::assertTrue($statement->execute([150, 100]));
            self::assertSame(2, $statement->rowCount());

            $combinedRows = $ztdPdo->query("SELECT name, amount FROM `{$combined}` ORDER BY name");
            $preparedRows = $ztdPdo->query("SELECT name, amount FROM `{$prepared}` ORDER BY name");
            self::assertNotFalse($combinedRows);
            self::assertNotFalse($preparedRows);
            self::assertSame([
                ['name' => 'Alice', 'amount' => '100.00'],
                ['name' => 'Bob', 'amount' => '150.00'],
                ['name' => 'Carol', 'amount' => '300.00'],
                ['name' => 'Dave', 'amount' => '50.00'],
            ], $combinedRows->fetchAll(PDO::FETCH_ASSOC));
            self::assertSame([
                ['name' => 'Bob', 'amount' => '150.00'],
                ['name' => 'Carol', 'amount' => '300.00'],
            ], $preparedRows->fetchAll(PDO::FETCH_ASSOC));

            $physicalCombined = $rawPdo->query("SELECT * FROM `{$combined}`");
            $physicalPrepared = $rawPdo->query("SELECT * FROM `{$prepared}`");
            self::assertNotFalse($physicalCombined);
            self::assertNotFalse($physicalPrepared);
            self::assertSame([], $physicalCombined->fetchAll(PDO::FETCH_ASSOC));
            self::assertSame([], $physicalPrepared->fetchAll(PDO::FETCH_ASSOC));
        } finally {
            $rawPdo->exec(sprintf('DROP DATABASE IF EXISTS `%s`', $databaseName));
        }
    }
}
