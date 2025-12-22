<?php

declare(strict_types=1);

namespace Tests\Integration\MySql\Pdo\UnknownSchema;

use Tests\Integration\MySqlIntegrationTestCase;
use ZtdQuery\Config\UnknownSchemaBehavior;
use ZtdQuery\Config\ZtdConfig;

/**
 * Tests for UnknownSchemaBehavior::EmptyResult mode.
 *
 * In EmptyResult mode, queries referencing unknown tables/columns return
 * an empty result set without errors.
 */
final class UnknownSchemaEmptyResultTest extends MySqlIntegrationTestCase
{
    protected function getZtdConfig(): ZtdConfig
    {
        return new ZtdConfig(
            unknownSchemaBehavior: UnknownSchemaBehavior::EmptyResult
        );
    }

    public function testSelectFromNonExistentTableReturnsEmptyResult(): void
    {
        $stmt = $this->ztdPdo->query('SELECT * FROM nonexistent_table_xyz123');

        $this->assertNotFalse($stmt);
        $rows = $stmt->fetchAll();
        $this->assertCount(0, $rows);
    }

    public function testSelectWithJoinToNonExistentTableReturnsEmptyResult(): void
    {
        $table = $this->uniqueTableName('users');
        $this->rawPdo->exec("CREATE TABLE `{$table}` (id INT PRIMARY KEY, name VARCHAR(255))");
        $this->rawPdo->exec("INSERT INTO `{$table}` (id, name) VALUES (1, 'Alice')");

        $stmt = $this->ztdPdo->query("SELECT * FROM `{$table}` JOIN nonexistent_table ON true");

        $this->assertNotFalse($stmt);
        $rows = $stmt->fetchAll();
        $this->assertCount(0, $rows);
    }

    public function testInsertToExistingTableWorks(): void
    {
        $table = $this->uniqueTableName('users');
        $this->rawPdo->exec("CREATE TABLE `{$table}` (id INT PRIMARY KEY, name VARCHAR(255))");

        $this->ztdPdo->exec("INSERT INTO `{$table}` (id, name) VALUES (1, 'Alice')");

        $rows = $this->ztdQuery("SELECT * FROM `{$table}`");
        $this->assertCount(1, $rows);
    }

    public function testUpdateExistingTableWorks(): void
    {
        $table = $this->uniqueTableName('users');
        $this->rawPdo->exec("CREATE TABLE `{$table}` (id INT PRIMARY KEY, name VARCHAR(255))");

        // Insert via shadow first
        $this->ztdPdo->exec("INSERT INTO `{$table}` (id, name) VALUES (1, 'Alice')");

        // Update the shadow row
        $this->ztdPdo->exec("UPDATE `{$table}` SET name = 'Bob' WHERE id = 1");

        $rows = $this->ztdQuery("SELECT * FROM `{$table}`");
        $this->assertCount(1, $rows);
        $this->assertEquals('Bob', $rows[0]['name']);
    }

    public function testDeleteFromExistingTableWorks(): void
    {
        $table = $this->uniqueTableName('users');
        $this->rawPdo->exec("CREATE TABLE `{$table}` (id INT PRIMARY KEY, name VARCHAR(255))");

        // Insert via shadow first
        $this->ztdPdo->exec("INSERT INTO `{$table}` (id, name) VALUES (1, 'Alice')");

        // Delete the shadow row
        $this->ztdPdo->exec("DELETE FROM `{$table}` WHERE id = 1");

        $rows = $this->ztdQuery("SELECT * FROM `{$table}`");
        $this->assertCount(0, $rows);
    }

    public function testSelectExistingTableReturnsData(): void
    {
        $table = $this->uniqueTableName('users');
        $this->rawPdo->exec("CREATE TABLE `{$table}` (id INT PRIMARY KEY, name VARCHAR(255))");
        $this->rawPdo->exec("INSERT INTO `{$table}` VALUES (1, 'Alice')");

        $rows = $this->ztdQuery("SELECT * FROM `{$table}`");

        $this->assertCount(1, $rows);
        $this->assertSame('Alice', $rows[0]['name']);
    }
}
