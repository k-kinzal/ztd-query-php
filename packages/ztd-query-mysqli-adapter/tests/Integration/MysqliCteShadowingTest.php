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
