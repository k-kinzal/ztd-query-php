<?php

declare(strict_types=1);

namespace Tests\Integration;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;
use Tests\Fixtures\MySqlContainer;
use ZtdQuery\Adapter\Mysqli\ZtdMysqli;

/**
 * Integration tests for ZtdMysqli: CTE shadowing and CRUD operations.
 *
 * Verifies that ZTD mode intercepts queries, applies CTE shadowing,
 * and does not modify the physical database.
 */
#[CoversNothing]
#[Large]
final class MysqliCteShadowingTest extends TestCase
{
    public function testExecuteQueryReplaceRemovesExistingPrimaryKey(): void
    {
        [$databaseName, $rawMysqli] = MySqlContainer::createTestDatabase();
        $table = 'prefix_' . bin2hex(random_bytes(8));
        $rawMysqli->query(sprintf('CREATE TABLE `%s` (id INT PRIMARY KEY, name VARCHAR(50))', $table));
        $ztdMysqli = ZtdMysqli::fromMysqli($rawMysqli, null);

        try {
            $ztdMysqli->query(sprintf("INSERT INTO `%s` VALUES (1, 'original')", $table));
            self::assertNotFalse($ztdMysqli->execute_query(
                sprintf('REPLACE INTO `%s` VALUES (?, ?)', $table),
                ['1', 'replaced'],
            ));

            $rows = $ztdMysqli->query(sprintf('SELECT * FROM `%s` WHERE id = 1', $table));
            self::assertInstanceOf(\mysqli_result::class, $rows);
            self::assertSame([['id' => 1, 'name' => 'replaced']], $rows->fetch_all(MYSQLI_ASSOC));
        } finally {
            $rawMysqli->query(sprintf('DROP DATABASE IF EXISTS `%s`', $databaseName));
        }
    }

    public function testExecuteQueryOnDuplicateKeyUpdateReplacesExistingValues(): void
    {
        [$databaseName, $rawMysqli] = MySqlContainer::createTestDatabase();
        $table = 'prefix_' . bin2hex(random_bytes(8));
        $rawMysqli->query(sprintf('CREATE TABLE `%s` (id INT PRIMARY KEY, name VARCHAR(50))', $table));
        $ztdMysqli = ZtdMysqli::fromMysqli($rawMysqli, null);

        try {
            $ztdMysqli->query(sprintf("INSERT INTO `%s` VALUES (1, 'original')", $table));
            self::assertNotFalse($ztdMysqli->execute_query(
                sprintf('INSERT INTO `%s` VALUES (?, ?) ON DUPLICATE KEY UPDATE name = VALUES(name)', $table),
                ['1', 'updated'],
            ));

            $rows = $ztdMysqli->query(sprintf('SELECT * FROM `%s` WHERE id = 1', $table));
            self::assertInstanceOf(\mysqli_result::class, $rows);
            self::assertSame([['id' => 1, 'name' => 'updated']], $rows->fetch_all(MYSQLI_ASSOC));
        } finally {
            $rawMysqli->query(sprintf('DROP DATABASE IF EXISTS `%s`', $databaseName));
        }
    }

    public function testUpdateReplacesExistingTextWithEmptyString(): void
    {
        [$databaseName, $rawMysqli] = MySqlContainer::createTestDatabase();
        $table = 'prefix_' . bin2hex(random_bytes(8));
        $rawMysqli->query(sprintf('CREATE TABLE `%s` (id INT PRIMARY KEY, name VARCHAR(100), notes TEXT)', $table));
        $ztdMysqli = ZtdMysqli::fromMysqli($rawMysqli, null);

        try {
            self::assertNotFalse($ztdMysqli->query(sprintf("INSERT INTO `%s` VALUES (1, 'Alice', 'some notes')", $table)));
            self::assertNotFalse($ztdMysqli->query(sprintf("UPDATE `%s` SET notes = '' WHERE name = 'Alice'", $table)));

            $result = $ztdMysqli->query(sprintf('SELECT notes FROM `%s` WHERE id = 1', $table));
            self::assertInstanceOf(\mysqli_result::class, $result);
            self::assertSame([['notes' => '']], $result->fetch_all(MYSQLI_ASSOC));
        } finally {
            $rawMysqli->query(sprintf('DROP DATABASE IF EXISTS `%s`', $databaseName));
        }
    }

    public function testSelfReferencingUpsertMatchesNativeMySql(): void
    {
        [$databaseName, $rawMysqli] = MySqlContainer::createTestDatabase();
        $nativeTable = 'prefix_' . bin2hex(random_bytes(8));
        $shadowTable = 'prefix_' . bin2hex(random_bytes(8));
        $rawMysqli->query(sprintf('CREATE TABLE `%s` (id INT PRIMARY KEY, quantity INT NOT NULL)', $nativeTable));
        $rawMysqli->query(sprintf('CREATE TABLE `%s` (id INT PRIMARY KEY, quantity INT NOT NULL)', $shadowTable));
        $ztdMysqli = ZtdMysqli::fromMysqli($rawMysqli, null);

        try {
            $rawMysqli->query(sprintf('INSERT INTO `%s` VALUES (1, 100)', $nativeTable));
            $ztdMysqli->query(sprintf('INSERT INTO `%s` VALUES (1, 100)', $shadowTable));
            $rawMysqli->query(sprintf('INSERT INTO `%1$s` VALUES (1, 5), (1, 7) ON DUPLICATE KEY UPDATE quantity = `%1$s`.quantity + VALUES(quantity)', $nativeTable));
            $ztdMysqli->query(sprintf('INSERT INTO `%1$s` VALUES (1, 5), (1, 7) ON DUPLICATE KEY UPDATE quantity = `%1$s`.quantity + VALUES(quantity)', $shadowTable));

            $rawRows = $rawMysqli->query(sprintf('SELECT id, quantity FROM `%s`', $nativeTable));
            $ztdRows = $ztdMysqli->query(sprintf('SELECT id, quantity FROM `%s`', $shadowTable));
            $physicalShadowRows = $rawMysqli->query(sprintf('SELECT * FROM `%s`', $shadowTable));
            self::assertInstanceOf(\mysqli_result::class, $rawRows);
            self::assertInstanceOf(\mysqli_result::class, $ztdRows);
            self::assertInstanceOf(\mysqli_result::class, $physicalShadowRows);
            self::assertEquals($rawRows->fetch_all(MYSQLI_ASSOC), $ztdRows->fetch_all(MYSQLI_ASSOC));
            self::assertSame([], $physicalShadowRows->fetch_all(MYSQLI_ASSOC));
        } finally {
            $rawMysqli->query(sprintf('DROP DATABASE IF EXISTS `%s`', $databaseName));
        }
    }

    public function testAffectedRowsCountsOnlyChangedMySqlRows(): void
    {
        [$databaseName, $rawMysqli] = MySqlContainer::createTestDatabase();
        $table = 'prefix_' . bin2hex(random_bytes(8));
        $rawMysqli->query(sprintf('CREATE TABLE `%s` (id INT PRIMARY KEY, score INT)', $table));
        $ztdMysqli = ZtdMysqli::fromMysqli($rawMysqli, null);

        try {
            $ztdMysqli->query(sprintf('INSERT INTO `%s` VALUES (1, 10)', $table));
            $ztdMysqli->query(sprintf('UPDATE `%s` SET score = 10 WHERE id = 1', $table));
            self::assertSame(0, $ztdMysqli->lastAffectedRows());
            $ztdMysqli->query(sprintf('UPDATE `%s` SET score = 11 WHERE id = 1', $table));
            self::assertSame(1, $ztdMysqli->lastAffectedRows());
        } finally {
            $rawMysqli->query(sprintf('DROP DATABASE IF EXISTS `%s`', $databaseName));
        }
    }

    public function testTransactionsAndSavepointsRestoreShadowRows(): void
    {
        [$databaseName, $rawMysqli] = MySqlContainer::createTestDatabase();
        $table = 'prefix_' . bin2hex(random_bytes(8));
        $rawMysqli->query(sprintf('CREATE TABLE `%s` (id INT PRIMARY KEY, name VARCHAR(20))', $table));
        $ztdMysqli = ZtdMysqli::fromMysqli($rawMysqli, null);

        try {
            $ztdMysqli->query(sprintf("INSERT INTO `%s` VALUES (1, 'one')", $table));
            $ztdMysqli->begin_transaction();
            $ztdMysqli->query(sprintf("INSERT INTO `%s` VALUES (2, 'two')", $table));
            $ztdMysqli->savepoint('nested');
            $ztdMysqli->query(sprintf("INSERT INTO `%s` VALUES (3, 'three')", $table));
            $ztdMysqli->query('ROLLBACK TO SAVEPOINT nested');
            $ztdMysqli->commit();

            $committed = $ztdMysqli->query(sprintf('SELECT * FROM `%s` ORDER BY id', $table));
            self::assertInstanceOf(\mysqli_result::class, $committed);
            self::assertSame([
                ['id' => 1, 'name' => 'one'],
                ['id' => 2, 'name' => 'two'],
            ], $committed->fetch_all(MYSQLI_ASSOC));

            $ztdMysqli->begin_transaction();
            $ztdMysqli->query(sprintf("UPDATE `%s` SET name = 'changed'", $table));
            $ztdMysqli->rollback();
            $rolledBack = $ztdMysqli->query(sprintf('SELECT * FROM `%s` ORDER BY id', $table));
            self::assertInstanceOf(\mysqli_result::class, $rolledBack);
            self::assertSame([
                ['id' => 1, 'name' => 'one'],
                ['id' => 2, 'name' => 'two'],
            ], $rolledBack->fetch_all(MYSQLI_ASSOC));
        } finally {
            $rawMysqli->query(sprintf('DROP DATABASE IF EXISTS `%s`', $databaseName));
        }
    }

    public function testRecursiveAndUserOwnedCteNamespacesRemainValid(): void
    {
        [$databaseName, $rawMysqli] = MySqlContainer::createTestDatabase();
        $table = 'prefix_' . bin2hex(random_bytes(8));
        $rawMysqli->query(sprintf('CREATE TABLE `%s` (id INT PRIMARY KEY, parent_id INT)', $table));
        $ztdMysqli = ZtdMysqli::fromMysqli($rawMysqli, null);

        try {
            $ztdMysqli->query(sprintf('INSERT INTO `%s` VALUES (1, NULL), (2, 1), (3, 2)', $table));
            $recursive = $ztdMysqli->query(sprintf(
                'WITH RECURSIVE tree AS (SELECT id, parent_id FROM `%1$s` WHERE parent_id IS NULL UNION ALL SELECT n.id, n.parent_id FROM `%1$s` n JOIN tree t ON n.parent_id = t.id) SELECT id FROM tree ORDER BY id',
                $table,
            ));
            self::assertInstanceOf(\mysqli_result::class, $recursive);
            self::assertSame([['id' => 1], ['id' => 2], ['id' => 3]], $recursive->fetch_all(MYSQLI_ASSOC));

            $owned = $ztdMysqli->query(sprintf('WITH `%1$s` AS (SELECT 9 AS id, NULL AS parent_id) SELECT id FROM `%1$s`', $table));
            self::assertInstanceOf(\mysqli_result::class, $owned);
            self::assertSame([['id' => 9]], $owned->fetch_all(MYSQLI_ASSOC));
        } finally {
            $rawMysqli->query(sprintf('DROP DATABASE IF EXISTS `%s`', $databaseName));
        }
    }

    public function testCteDefinitionsRemainVisibleToSimulatedDml(): void
    {
        [$databaseName, $rawMysqli] = MySqlContainer::createTestDatabase();
        $table = 'prefix_' . bin2hex(random_bytes(8));
        $rawMysqli->query(sprintf('CREATE TABLE `%s` (id INT PRIMARY KEY, value VARCHAR(20))', $table));
        $ztdMysqli = ZtdMysqli::fromMysqli($rawMysqli, null);

        try {
            self::assertNotFalse($ztdMysqli->query(sprintf("WITH source AS (SELECT 1 AS id, 'one' AS value UNION ALL SELECT 2, 'two') INSERT INTO `%s` SELECT * FROM source", $table)));
            self::assertNotFalse($ztdMysqli->query(sprintf("WITH chosen AS (SELECT id FROM `%1\$s` WHERE value = 'two') UPDATE `%1\$s` SET value = 'changed' WHERE id IN (SELECT id FROM chosen)", $table)));
            self::assertNotFalse($ztdMysqli->query(sprintf("WITH chosen AS (SELECT id FROM `%1\$s` WHERE value = 'one') DELETE FROM `%1\$s` WHERE id IN (SELECT id FROM chosen)", $table)));

            $result = $ztdMysqli->query(sprintf('SELECT * FROM `%s`', $table));
            self::assertInstanceOf(\mysqli_result::class, $result);
            self::assertSame([['id' => 2, 'value' => 'changed']], $result->fetch_all(MYSQLI_ASSOC));
        } finally {
            $rawMysqli->query(sprintf('DROP DATABASE IF EXISTS `%s`', $databaseName));
        }
    }

    public function testOrderedLimitedUpdateKeepsOriginalIdentityAndSwapSnapshot(): void
    {
        [$databaseName, $rawMysqli] = MySqlContainer::createTestDatabase();
        $table = 'prefix_' . bin2hex(random_bytes(8));
        $rawMysqli->query(sprintf('CREATE TABLE `%s` (id INT PRIMARY KEY, left_value VARCHAR(20), right_value VARCHAR(20))', $table));
        $ztdMysqli = ZtdMysqli::fromMysqli($rawMysqli, null);

        try {
            $ztdMysqli->query(sprintf("INSERT INTO `%s` VALUES (1, 'a1', 'b1'), (2, 'a2', 'b2'), (3, 'a3', 'b3')", $table));
            $ztdMysqli->query(sprintf('UPDATE `%s` SET id = 30, left_value = right_value, right_value = left_value ORDER BY id DESC LIMIT 1', $table));

            $result = $ztdMysqli->query(sprintf('SELECT * FROM `%s` ORDER BY id', $table));
            self::assertInstanceOf(\mysqli_result::class, $result);
            self::assertSame([
                ['id' => 1, 'left_value' => 'a1', 'right_value' => 'b1'],
                ['id' => 2, 'left_value' => 'a2', 'right_value' => 'b2'],
                ['id' => 30, 'left_value' => 'b3', 'right_value' => 'a3'],
            ], $result->fetch_all(MYSQLI_ASSOC));
        } finally {
            $rawMysqli->query(sprintf('DROP DATABASE IF EXISTS `%s`', $databaseName));
        }
    }

    public function testInsertSelectPreservesStarJoinsAggregatesDistinctAndRollup(): void
    {
        [$databaseName, $rawMysqli] = MySqlContainer::createTestDatabase();
        $source = 'prefix_' . bin2hex(random_bytes(8));
        $target = 'prefix_' . bin2hex(random_bytes(8));
        $orders = 'prefix_' . bin2hex(random_bytes(8));
        $summary = 'prefix_' . bin2hex(random_bytes(8));
        $regions = 'prefix_' . bin2hex(random_bytes(8));
        $rawMysqli->query(sprintf('CREATE TABLE `%s` (id INT PRIMARY KEY, region VARCHAR(20), amount INT)', $source));
        $rawMysqli->query(sprintf('CREATE TABLE `%s` (id INT PRIMARY KEY, region VARCHAR(20), amount INT)', $target));
        $rawMysqli->query(sprintf('CREATE TABLE `%s` (id INT PRIMARY KEY, source_id INT)', $orders));
        $rawMysqli->query(sprintf('CREATE TABLE `%s` (region VARCHAR(20), order_count INT, total_amount INT)', $summary));
        $rawMysqli->query(sprintf('CREATE TABLE `%s` (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(20))', $regions));
        $ztdMysqli = ZtdMysqli::fromMysqli($rawMysqli, null);

        try {
            $ztdMysqli->query(sprintf("INSERT INTO `%s` VALUES (1, 'east', 100), (2, 'east', 200), (3, 'west', 300)", $source));
            $ztdMysqli->query(sprintf('INSERT INTO `%s` VALUES (1, 1), (2, 1), (3, 3)', $orders));
            $ztdMysqli->query(sprintf('INSERT INTO `%s` SELECT * FROM `%s`', $target, $source));
            $ztdMysqli->query(sprintf(
                'INSERT INTO `%s` (region, order_count, total_amount) SELECT s.region, COUNT(o.id), SUM(s.amount) FROM `%s` s JOIN `%s` o ON o.source_id = s.id GROUP BY s.region WITH ROLLUP',
                $summary,
                $source,
                $orders,
            ));
            $ztdMysqli->query(sprintf('INSERT INTO `%s` (name) SELECT DISTINCT region FROM `%s` ORDER BY region', $regions, $source));

            $targetRows = $ztdMysqli->query(sprintf('SELECT * FROM `%s` ORDER BY id', $target));
            $summaryRows = $ztdMysqli->query(sprintf('SELECT * FROM `%s` ORDER BY region', $summary));
            $regionRows = $ztdMysqli->query(sprintf('SELECT * FROM `%s` ORDER BY id', $regions));
            self::assertInstanceOf(\mysqli_result::class, $targetRows);
            self::assertInstanceOf(\mysqli_result::class, $summaryRows);
            self::assertInstanceOf(\mysqli_result::class, $regionRows);
            self::assertEquals([
                ['id' => 1, 'region' => 'east', 'amount' => 100],
                ['id' => 2, 'region' => 'east', 'amount' => 200],
                ['id' => 3, 'region' => 'west', 'amount' => 300],
            ], $targetRows->fetch_all(MYSQLI_ASSOC));
            self::assertEquals([
                ['region' => null, 'order_count' => 3, 'total_amount' => 500],
                ['region' => 'east', 'order_count' => 2, 'total_amount' => 200],
                ['region' => 'west', 'order_count' => 1, 'total_amount' => 300],
            ], $summaryRows->fetch_all(MYSQLI_ASSOC));
            self::assertEquals([['id' => 1, 'name' => 'east'], ['id' => 2, 'name' => 'west']], $regionRows->fetch_all(MYSQLI_ASSOC));
        } finally {
            $rawMysqli->query(sprintf('DROP DATABASE IF EXISTS `%s`', $databaseName));
        }
    }

    public function testAutoIncrementUsesShadowCounterWithoutModifyingPhysicalTable(): void
    {
        [$databaseName, $rawMysqli] = MySqlContainer::createTestDatabase();
        $table = 'prefix_' . bin2hex(random_bytes(8));
        $rawMysqli->query(sprintf('CREATE TABLE `%s` (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(20) NOT NULL)', $table));
        $ztdMysqli = ZtdMysqli::fromMysqli($rawMysqli, null);

        try {
            $ztdMysqli->query(sprintf("INSERT INTO `%s` (name) VALUES ('Alice'), ('Bob')", $table));

            $ztdRows = $ztdMysqli->query(sprintf('SELECT id, name FROM `%s` ORDER BY id', $table));
            $rawRows = $rawMysqli->query(sprintf('SELECT * FROM `%s`', $table));
            self::assertInstanceOf(\mysqli_result::class, $ztdRows);
            self::assertInstanceOf(\mysqli_result::class, $rawRows);
            self::assertEquals([['id' => 1, 'name' => 'Alice'], ['id' => 2, 'name' => 'Bob']], $ztdRows->fetch_all(MYSQLI_ASSOC));
            self::assertSame([], $rawRows->fetch_all(MYSQLI_ASSOC));
        } finally {
            $rawMysqli->query(sprintf('DROP DATABASE IF EXISTS `%s`', $databaseName));
        }
    }

    public function testOmittedExplicitAndDefaultOnlyValuesMatchMySql(): void
    {
        [$databaseName, $rawMysqli] = MySqlContainer::createTestDatabase();
        $table = 'prefix_' . bin2hex(random_bytes(8));
        $rawMysqli->query(sprintf(
            "CREATE TABLE `%s` (id INT DEFAULT 7, status ENUM('new','active') DEFAULT 'active', note VARCHAR(20) DEFAULT NULL)",
            $table,
        ));
        $ztdMysqli = ZtdMysqli::fromMysqli($rawMysqli, null);

        try {
            $rawMysqli->query(sprintf("INSERT INTO `%s` (id, status) VALUES (1, DEFAULT)", $table));
            $rawMysqli->query(sprintf('INSERT INTO `%s` () VALUES ()', $table));
            $ztdMysqli->query(sprintf("INSERT INTO `%s` (id, status) VALUES (1, DEFAULT)", $table));
            $ztdMysqli->query(sprintf('INSERT INTO `%s` () VALUES ()', $table));

            $raw = $rawMysqli->query(sprintf('SELECT * FROM `%s` ORDER BY id', $table));
            $ztd = $ztdMysqli->query(sprintf('SELECT * FROM `%s` ORDER BY id', $table));
            self::assertInstanceOf(\mysqli_result::class, $raw);
            self::assertInstanceOf(\mysqli_result::class, $ztd);
            $rawRows = $raw->fetch_all(MYSQLI_ASSOC);
            $ztdRows = $ztd->fetch_all(MYSQLI_ASSOC);
            self::assertEquals($rawRows, $ztdRows);
        } finally {
            $rawMysqli->query(sprintf('DROP DATABASE IF EXISTS `%s`', $databaseName));
        }
    }

    public function testEnumUsesDeclarationRanksForOrderingAndComparison(): void
    {
        [$databaseName, $rawMysqli] = MySqlContainer::createTestDatabase();
        $table = 'prefix_' . bin2hex(random_bytes(8));
        $rawMysqli->query(sprintf("CREATE TABLE `%s` (id INT PRIMARY KEY, size ENUM('small','medium','large'))", $table));
        $ztdMysqli = ZtdMysqli::fromMysqli($rawMysqli, null);

        try {
            $ztdMysqli->query(sprintf("INSERT INTO `%s` VALUES (1, 'large'), (2, 'small'), (3, 'medium')", $table));

            $ordered = $ztdMysqli->query(sprintf('SELECT size FROM `%s` ORDER BY size', $table));
            $compared = $ztdMysqli->query(sprintf("SELECT size FROM `%s` WHERE size > 'small' ORDER BY size", $table));
            self::assertInstanceOf(\mysqli_result::class, $ordered);
            self::assertInstanceOf(\mysqli_result::class, $compared);
            self::assertSame(['small', 'medium', 'large'], array_column($ordered->fetch_all(MYSQLI_ASSOC), 'size'));
            self::assertSame(['medium', 'large'], array_column($compared->fetch_all(MYSQLI_ASSOC), 'size'));
        } finally {
            $rawMysqli->query(sprintf('DROP DATABASE IF EXISTS `%s`', $databaseName));
        }
    }

    public function testSetExpressionsRemainSingleStatements(): void
    {
        [$databaseName, $rawMysqli] = MySqlContainer::createTestDatabase();
        $users = 'prefix_' . bin2hex(random_bytes(8));
        $vip = 'prefix_' . bin2hex(random_bytes(8));
        $rawMysqli->query(sprintf('CREATE TABLE `%s` (name VARCHAR(255) PRIMARY KEY)', $users));
        $rawMysqli->query(sprintf('CREATE TABLE `%s` (name VARCHAR(255) PRIMARY KEY)', $vip));
        $ztdMysqli = ZtdMysqli::fromMysqli($rawMysqli, null);

        try {
            $ztdMysqli->query(sprintf("INSERT INTO `%s` (name) VALUES ('Alice'), ('Bob')", $users));
            $ztdMysqli->query(sprintf("INSERT INTO `%s` (name) VALUES ('Alice')", $vip));

            $except = $ztdMysqli->query(sprintf(
                'SELECT name FROM `%s` EXCEPT SELECT name FROM `%s` ORDER BY name',
                $users,
                $vip,
            ));
            $intersect = $ztdMysqli->query(sprintf(
                'SELECT name FROM `%s` INTERSECT SELECT name FROM `%s` ORDER BY name',
                $users,
                $vip,
            ));
            self::assertInstanceOf(\mysqli_result::class, $except);
            self::assertInstanceOf(\mysqli_result::class, $intersect);
            self::assertSame([['name' => 'Bob']], $except->fetch_all(MYSQLI_ASSOC));
            self::assertSame([['name' => 'Alice']], $intersect->fetch_all(MYSQLI_ASSOC));
        } finally {
            $rawMysqli->query(sprintf('DROP DATABASE IF EXISTS `%s`', $databaseName));
        }
    }

    public function testSelectOnCleanShadowReturnsEmpty(): void
    {
        [$databaseName, $rawMysqli] = MySqlContainer::createTestDatabase();
        $table = 'prefix_' . bin2hex(random_bytes(8));
        $rawMysqli->query(sprintf('CREATE TABLE `%s` (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(255) NOT NULL, age INT NOT NULL)', $table));
        $rawMysqli->query(sprintf("INSERT INTO `%s` (name, age) VALUES ('Alice', 30), ('Bob', 25)", $table));
        $ztdMysqli = ZtdMysqli::fromMysqli($rawMysqli, null);
        try {
            $result = $ztdMysqli->query(sprintf('SELECT * FROM `%s` ORDER BY id', $table));
            self::assertNotFalse($result);
            self::assertInstanceOf(\mysqli_result::class, $result);

            /** @var list<array<string, mixed>> $rows */
            $rows = $result->fetch_all(MYSQLI_ASSOC);
            self::assertCount(0, $rows);
        } finally {
            $rawMysqli->query(sprintf('DROP DATABASE IF EXISTS `%s`', $databaseName));
        }
    }

    public function testInsertDoesNotModifyPhysicalDatabase(): void
    {
        [$databaseName, $rawMysqli] = MySqlContainer::createTestDatabase();
        $table = 'prefix_' . bin2hex(random_bytes(8));
        $rawMysqli->query(sprintf('CREATE TABLE `%s` (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(255) NOT NULL, age INT NOT NULL)', $table));
        $rawMysqli->query(sprintf("INSERT INTO `%s` (name, age) VALUES ('Alice', 30), ('Bob', 25)", $table));
        $ztdMysqli = ZtdMysqli::fromMysqli($rawMysqli, null);
        try {
            $ztdMysqli->query(sprintf(
                "INSERT INTO `%s` (name, age) VALUES ('Charlie', 35)",
                $table
            ));

            $result = $rawMysqli->query(sprintf('SELECT * FROM `%s`', $table));
            self::assertNotFalse($result);
            self::assertInstanceOf(\mysqli_result::class, $result);

            /** @var list<array<string, mixed>> $rows */
            $rows = $result->fetch_all(MYSQLI_ASSOC);
            self::assertCount(2, $rows);
        } finally {
            $rawMysqli->query(sprintf('DROP DATABASE IF EXISTS `%s`', $databaseName));
        }
    }

    public function testInsertIsVisibleViaZtdSelect(): void
    {
        [$databaseName, $rawMysqli] = MySqlContainer::createTestDatabase();
        $table = 'prefix_' . bin2hex(random_bytes(8));
        $rawMysqli->query(sprintf('CREATE TABLE `%s` (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(255) NOT NULL, age INT NOT NULL)', $table));
        $rawMysqli->query(sprintf("INSERT INTO `%s` (name, age) VALUES ('Alice', 30), ('Bob', 25)", $table));
        $ztdMysqli = ZtdMysqli::fromMysqli($rawMysqli, null);
        try {
            $ztdMysqli->query(sprintf(
                "INSERT INTO `%s` (name, age) VALUES ('Charlie', 35)",
                $table
            ));

            $result = $ztdMysqli->query(sprintf('SELECT * FROM `%s` ORDER BY id', $table));
            self::assertNotFalse($result);
            self::assertInstanceOf(\mysqli_result::class, $result);

            /** @var list<array<string, mixed>> $rows */
            $rows = $result->fetch_all(MYSQLI_ASSOC);
            self::assertCount(1, $rows);
            self::assertSame('Charlie', $rows[0]['name']);
            /** @var string|int $age */
            $age = $rows[0]['age'];
            self::assertSame('35', (string) $age);
        } finally {
            $rawMysqli->query(sprintf('DROP DATABASE IF EXISTS `%s`', $databaseName));
        }
    }

    public function testMultipleInsertsAccumulate(): void
    {
        [$databaseName, $rawMysqli] = MySqlContainer::createTestDatabase();
        $table = 'prefix_' . bin2hex(random_bytes(8));
        $rawMysqli->query(sprintf('CREATE TABLE `%s` (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(255) NOT NULL, age INT NOT NULL)', $table));
        $rawMysqli->query(sprintf("INSERT INTO `%s` (name, age) VALUES ('Alice', 30), ('Bob', 25)", $table));
        $ztdMysqli = ZtdMysqli::fromMysqli($rawMysqli, null);
        try {
            $ztdMysqli->query(sprintf(
                "INSERT INTO `%s` (name, age) VALUES ('Charlie', 35)",
                $table
            ));
            $ztdMysqli->query(sprintf(
                "INSERT INTO `%s` (name, age) VALUES ('Diana', 28)",
                $table
            ));

            $result = $ztdMysqli->query(sprintf('SELECT * FROM `%s` ORDER BY name', $table));
            self::assertNotFalse($result);
            self::assertInstanceOf(\mysqli_result::class, $result);

            /** @var list<array<string, mixed>> $rows */
            $rows = $result->fetch_all(MYSQLI_ASSOC);
            self::assertCount(2, $rows);

            $names = array_column($rows, 'name');
            self::assertContains('Charlie', $names);
            self::assertContains('Diana', $names);
        } finally {
            $rawMysqli->query(sprintf('DROP DATABASE IF EXISTS `%s`', $databaseName));
        }
    }

    public function testSelectWithWhereOnShadowData(): void
    {
        [$databaseName, $rawMysqli] = MySqlContainer::createTestDatabase();
        $table = 'prefix_' . bin2hex(random_bytes(8));
        $rawMysqli->query(sprintf('CREATE TABLE `%s` (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(255) NOT NULL, age INT NOT NULL)', $table));
        $rawMysqli->query(sprintf("INSERT INTO `%s` (name, age) VALUES ('Alice', 30), ('Bob', 25)", $table));
        $ztdMysqli = ZtdMysqli::fromMysqli($rawMysqli, null);
        try {
            $ztdMysqli->query(sprintf(
                "INSERT INTO `%s` (name, age) VALUES ('Charlie', 35)",
                $table
            ));
            $ztdMysqli->query(sprintf(
                "INSERT INTO `%s` (name, age) VALUES ('Diana', 28)",
                $table
            ));

            $result = $ztdMysqli->query(sprintf("SELECT * FROM `%s` WHERE age > 30", $table));
            self::assertNotFalse($result);
            self::assertInstanceOf(\mysqli_result::class, $result);

            /** @var list<array<string, mixed>> $rows */
            $rows = $result->fetch_all(MYSQLI_ASSOC);
            self::assertCount(1, $rows);
            self::assertSame('Charlie', $rows[0]['name']);
        } finally {
            $rawMysqli->query(sprintf('DROP DATABASE IF EXISTS `%s`', $databaseName));
        }
    }

    public function testPhysicalDatabaseRemainsUnchangedAfterMutations(): void
    {
        [$databaseName, $rawMysqli] = MySqlContainer::createTestDatabase();
        $table = 'prefix_' . bin2hex(random_bytes(8));
        $rawMysqli->query(sprintf('CREATE TABLE `%s` (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(255) NOT NULL, age INT NOT NULL)', $table));
        $rawMysqli->query(sprintf("INSERT INTO `%s` (name, age) VALUES ('Alice', 30), ('Bob', 25)", $table));
        $ztdMysqli = ZtdMysqli::fromMysqli($rawMysqli, null);
        try {
            $ztdMysqli->query(sprintf(
                "INSERT INTO `%s` (name, age) VALUES ('Charlie', 35)",
                $table
            ));
            $ztdMysqli->query(sprintf(
                "INSERT INTO `%s` (name, age) VALUES ('Diana', 28)",
                $table
            ));

            $result = $rawMysqli->query(sprintf('SELECT * FROM `%s` ORDER BY id', $table));
            self::assertNotFalse($result);
            self::assertInstanceOf(\mysqli_result::class, $result);

            /** @var list<array<string, mixed>> $rows */
            $rows = $result->fetch_all(MYSQLI_ASSOC);
            self::assertCount(2, $rows);
            self::assertSame('Alice', $rows[0]['name']);
            self::assertSame('Bob', $rows[1]['name']);
        } finally {
            $rawMysqli->query(sprintf('DROP DATABASE IF EXISTS `%s`', $databaseName));
        }
    }

    public function testEnableDisableToggle(): void
    {
        [$databaseName, $rawMysqli] = MySqlContainer::createTestDatabase();
        $table = 'prefix_' . bin2hex(random_bytes(8));
        $rawMysqli->query(sprintf('CREATE TABLE `%s` (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(255) NOT NULL, age INT NOT NULL)', $table));
        $rawMysqli->query(sprintf("INSERT INTO `%s` (name, age) VALUES ('Alice', 30), ('Bob', 25)", $table));
        $ztdMysqli = ZtdMysqli::fromMysqli($rawMysqli, null);
        try {
            self::assertTrue($ztdMysqli->isZtdEnabled());

            $ztdMysqli->disableZtd();
            self::assertFalse($ztdMysqli->isZtdEnabled());

            $ztdMysqli->enableZtd();
            self::assertTrue($ztdMysqli->isZtdEnabled());
        } finally {
            $rawMysqli->query(sprintf('DROP DATABASE IF EXISTS `%s`', $databaseName));
        }
    }

    public function testDisableZtdBypassesRewriting(): void
    {
        [$databaseName, $rawMysqli] = MySqlContainer::createTestDatabase();
        $table = 'prefix_' . bin2hex(random_bytes(8));
        $rawMysqli->query(sprintf('CREATE TABLE `%s` (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(255) NOT NULL, age INT NOT NULL)', $table));
        $rawMysqli->query(sprintf("INSERT INTO `%s` (name, age) VALUES ('Alice', 30), ('Bob', 25)", $table));
        $ztdMysqli = ZtdMysqli::fromMysqli($rawMysqli, null);
        try {
            $ztdMysqli->disableZtd();

            $ztdMysqli->query(sprintf(
                "INSERT INTO `%s` (name, age) VALUES ('Direct', 40)",
                $table
            ));

            $result = $rawMysqli->query(sprintf('SELECT * FROM `%s`', $table));
            self::assertNotFalse($result);
            self::assertInstanceOf(\mysqli_result::class, $result);

            /** @var list<array<string, mixed>> $rows */
            $rows = $result->fetch_all(MYSQLI_ASSOC);
            self::assertCount(3, $rows);

            $rawMysqli->query(sprintf("DELETE FROM `%s` WHERE name = 'Direct'", $table));
        } finally {
            $rawMysqli->query(sprintf('DROP DATABASE IF EXISTS `%s`', $databaseName));
        }
    }

    public function testPreparedStatementSelectWithZtd(): void
    {
        [$databaseName, $rawMysqli] = MySqlContainer::createTestDatabase();
        $table = 'prefix_' . bin2hex(random_bytes(8));
        $rawMysqli->query(sprintf('CREATE TABLE `%s` (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(255) NOT NULL, age INT NOT NULL)', $table));
        $rawMysqli->query(sprintf("INSERT INTO `%s` (name, age) VALUES ('Alice', 30), ('Bob', 25)", $table));
        $ztdMysqli = ZtdMysqli::fromMysqli($rawMysqli, null);
        try {
            $ztdMysqli->query(sprintf(
                "INSERT INTO `%s` (name, age) VALUES ('Charlie', 35)",
                $table
            ));

            $stmt = $ztdMysqli->prepare(sprintf('SELECT * FROM `%s` WHERE name = ?', $table));
            self::assertNotFalse($stmt);

            $name = 'Charlie';
            $stmt->bind_param('s', $name);
            $stmt->execute();
            $result = $stmt->get_result();
            self::assertNotFalse($result);

            /** @var list<array<string, mixed>> $rows */
            $rows = $result->fetch_all(MYSQLI_ASSOC);
            self::assertCount(1, $rows);
            self::assertSame('Charlie', $rows[0]['name']);
        } finally {
            $rawMysqli->query(sprintf('DROP DATABASE IF EXISTS `%s`', $databaseName));
        }
    }

    public function testPreparedStatementSelectNonExistent(): void
    {
        [$databaseName, $rawMysqli] = MySqlContainer::createTestDatabase();
        $table = 'prefix_' . bin2hex(random_bytes(8));
        $rawMysqli->query(sprintf('CREATE TABLE `%s` (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(255) NOT NULL, age INT NOT NULL)', $table));
        $rawMysqli->query(sprintf("INSERT INTO `%s` (name, age) VALUES ('Alice', 30), ('Bob', 25)", $table));
        $ztdMysqli = ZtdMysqli::fromMysqli($rawMysqli, null);
        try {
            $ztdMysqli->query(sprintf(
                "INSERT INTO `%s` (name, age) VALUES ('Charlie', 35)",
                $table
            ));

            $stmt = $ztdMysqli->prepare(sprintf('SELECT * FROM `%s` WHERE name = ?', $table));
            self::assertNotFalse($stmt);

            $name = 'Alice';
            $stmt->bind_param('s', $name);
            $stmt->execute();
            $result = $stmt->get_result();
            self::assertNotFalse($result);

            /** @var list<array<string, mixed>> $rows */
            $rows = $result->fetch_all(MYSQLI_ASSOC);
            self::assertCount(0, $rows);
        } finally {
            $rawMysqli->query(sprintf('DROP DATABASE IF EXISTS `%s`', $databaseName));
        }
    }

    public function testAffectedRowsAfterInsert(): void
    {
        [$databaseName, $rawMysqli] = MySqlContainer::createTestDatabase();
        $table = 'prefix_' . bin2hex(random_bytes(8));
        $rawMysqli->query(sprintf('CREATE TABLE `%s` (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(255) NOT NULL, age INT NOT NULL)', $table));
        $rawMysqli->query(sprintf("INSERT INTO `%s` (name, age) VALUES ('Alice', 30), ('Bob', 25)", $table));
        $ztdMysqli = ZtdMysqli::fromMysqli($rawMysqli, null);
        try {
            $ztdMysqli->query(sprintf(
                "INSERT INTO `%s` (name, age) VALUES ('Charlie', 35)",
                $table
            ));

            self::assertSame(1, $ztdMysqli->lastAffectedRows());
        } finally {
            $rawMysqli->query(sprintf('DROP DATABASE IF EXISTS `%s`', $databaseName));
        }
    }

    public function testAffectedRowsAfterMultipleInserts(): void
    {
        [$databaseName, $rawMysqli] = MySqlContainer::createTestDatabase();
        $table = 'prefix_' . bin2hex(random_bytes(8));
        $rawMysqli->query(sprintf('CREATE TABLE `%s` (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(255) NOT NULL, age INT NOT NULL)', $table));
        $rawMysqli->query(sprintf("INSERT INTO `%s` (name, age) VALUES ('Alice', 30), ('Bob', 25)", $table));
        $ztdMysqli = ZtdMysqli::fromMysqli($rawMysqli, null);
        try {
            $ztdMysqli->query(sprintf(
                "INSERT INTO `%s` (name, age) VALUES ('Charlie', 35)",
                $table
            ));
            self::assertSame(1, $ztdMysqli->lastAffectedRows());

            $ztdMysqli->query(sprintf(
                "INSERT INTO `%s` (name, age) VALUES ('Diana', 28)",
                $table
            ));
            self::assertSame(1, $ztdMysqli->lastAffectedRows());
        } finally {
            $rawMysqli->query(sprintf('DROP DATABASE IF EXISTS `%s`', $databaseName));
        }
    }

    public function testExecuteQuerySelect(): void
    {
        [$databaseName, $rawMysqli] = MySqlContainer::createTestDatabase();
        $table = 'prefix_' . bin2hex(random_bytes(8));
        $rawMysqli->query(sprintf('CREATE TABLE `%s` (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(255) NOT NULL, age INT NOT NULL)', $table));
        $rawMysqli->query(sprintf("INSERT INTO `%s` (name, age) VALUES ('Alice', 30), ('Bob', 25)", $table));
        $ztdMysqli = ZtdMysqli::fromMysqli($rawMysqli, null);
        try {
            $ztdMysqli->query(sprintf(
                "INSERT INTO `%s` (name, age) VALUES ('Charlie', 35)",
                $table
            ));

            $result = $ztdMysqli->execute_query(
                sprintf('SELECT * FROM `%s` WHERE name = ?', $table),
                ['Charlie']
            );
            self::assertNotFalse($result);
            self::assertInstanceOf(\mysqli_result::class, $result);

            /** @var list<array<string, mixed>> $rows */
            $rows = $result->fetch_all(MYSQLI_ASSOC);
            self::assertCount(1, $rows);
            self::assertSame('Charlie', $rows[0]['name']);
        } finally {
            $rawMysqli->query(sprintf('DROP DATABASE IF EXISTS `%s`', $databaseName));
        }
    }

    public function testRealQueryInsert(): void
    {
        [$databaseName, $rawMysqli] = MySqlContainer::createTestDatabase();
        $table = 'prefix_' . bin2hex(random_bytes(8));
        $rawMysqli->query(sprintf('CREATE TABLE `%s` (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(255) NOT NULL, age INT NOT NULL)', $table));
        $rawMysqli->query(sprintf("INSERT INTO `%s` (name, age) VALUES ('Alice', 30), ('Bob', 25)", $table));
        $ztdMysqli = ZtdMysqli::fromMysqli($rawMysqli, null);
        try {
            $result = $ztdMysqli->real_query(sprintf(
                "INSERT INTO `%s` (name, age) VALUES ('Charlie', 35)",
                $table
            ));
            self::assertTrue($result);

            $selectResult = $ztdMysqli->query(sprintf('SELECT * FROM `%s`', $table));
            self::assertNotFalse($selectResult);
            self::assertInstanceOf(\mysqli_result::class, $selectResult);

            /** @var list<array<string, mixed>> $rows */
            $rows = $selectResult->fetch_all(MYSQLI_ASSOC);
            self::assertCount(1, $rows);
        } finally {
            $rawMysqli->query(sprintf('DROP DATABASE IF EXISTS `%s`', $databaseName));
        }
    }

    public function testPreparedBackslashesRoundTripWithoutMysqlEscapeCorruption(): void
    {
        [$databaseName, $rawMysqli] = MySqlContainer::createTestDatabase();
        $table = 'typed_' . bin2hex(random_bytes(8));
        $rawMysqli->query(sprintf('CREATE TABLE `%s` (id INT PRIMARY KEY, value VARCHAR(255))', $table));
        $ztdMysqli = ZtdMysqli::fromMysqli($rawMysqli, null);

        try {
            $insert = $ztdMysqli->prepare(sprintf('INSERT INTO `%s` (id, value) VALUES (?, ?)', $table));
            self::assertNotFalse($insert);
            $id = 1;
            $value = 'path\\to\\file';
            self::assertTrue($insert->bind_param('is', $id, $value));
            self::assertTrue($insert->execute());

            $result = $ztdMysqli->query(sprintf('SELECT value FROM `%s` WHERE id = 1', $table));
            self::assertNotFalse($result);
            self::assertInstanceOf(\mysqli_result::class, $result);
            self::assertSame([['value' => $value]], $result->fetch_all(MYSQLI_ASSOC));
        } finally {
            $rawMysqli->query(sprintf('DROP DATABASE IF EXISTS `%s`', $databaseName));
        }
    }
}
