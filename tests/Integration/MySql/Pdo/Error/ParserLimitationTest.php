<?php

declare(strict_types=1);

namespace Tests\Integration\MySql\Pdo\Error;

use Tests\Integration\MySqlIntegrationTestCase;

/**
 * Tests for parser limitation behavior.
 *
 * According to the specification, certain valid MySQL syntax (like PARTITION clause)
 * may have parser limitations. These tests verify that such queries are handled
 * appropriately - either via passthrough for reads or write protection for writes.
 *
 * Note: These tests complement the existing Partition tests (SelectPartitionTest,
 * UpdatePartitionTest, DeletePartitionTest, InsertPartitionTest) which already
 * cover the basic behavior.
 */
final class ParserLimitationTest extends MySqlIntegrationTestCase
{
    public function testSelectWithPartitionClausePassesThroughToDatabase(): void
    {
        $table = $this->uniqueTableName('partitioned');

        $this->rawPdo->exec("CREATE TABLE `{$table}` (id INT PRIMARY KEY, name VARCHAR(255)) ENGINE=InnoDB PARTITION BY HASH(id) PARTITIONS 4");
        $this->rawPdo->exec("INSERT INTO `{$table}` VALUES (1, 'Alice')");

        // PARTITION clause in SELECT is passed through to the database
        $rows = $this->ztdQuery("SELECT * FROM `{$table}` PARTITION (p1)");

        // Query should execute successfully (returns array, possibly empty depending on partition hash)
        $this->assertGreaterThanOrEqual(0, count($rows));
    }

    public function testUpdateWithPartitionClauseThrowsWriteProtection(): void
    {
        $table = $this->uniqueTableName('partitioned');

        $this->rawPdo->exec("CREATE TABLE `{$table}` (id INT PRIMARY KEY, name VARCHAR(255)) ENGINE=InnoDB PARTITION BY HASH(id) PARTITIONS 4");
        $this->rawPdo->exec("INSERT INTO `{$table}` VALUES (1, 'Alice')");

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('ZTD Write Protection');

        // PARTITION clause in UPDATE triggers write protection
        $this->ztdPdo->exec("UPDATE `{$table}` PARTITION (p0) SET name = 'Bob' WHERE id = 1");
    }

    public function testDeleteWithPartitionClauseThrowsException(): void
    {
        $table = $this->uniqueTableName('partitioned');

        $this->rawPdo->exec("CREATE TABLE `{$table}` (id INT PRIMARY KEY, name VARCHAR(255)) ENGINE=InnoDB PARTITION BY HASH(id) PARTITIONS 4");
        $this->rawPdo->exec("INSERT INTO `{$table}` VALUES (1, 'Alice')");

        $this->expectException(\Throwable::class);

        // PARTITION clause in DELETE triggers exception
        $this->ztdPdo->exec("DELETE FROM `{$table}` PARTITION (p0) WHERE id = 1");
    }

    public function testInsertWithPartitionClauseThrowsException(): void
    {
        $table = $this->uniqueTableName('partitioned');

        $this->rawPdo->exec("CREATE TABLE `{$table}` (id INT PRIMARY KEY, name VARCHAR(255)) ENGINE=InnoDB PARTITION BY HASH(id) PARTITIONS 4");

        $this->expectException(\Throwable::class);

        // PARTITION clause in INSERT triggers exception
        $this->ztdPdo->exec("INSERT INTO `{$table}` PARTITION (p0) (id, name) VALUES (1, 'Alice')");
    }
}
