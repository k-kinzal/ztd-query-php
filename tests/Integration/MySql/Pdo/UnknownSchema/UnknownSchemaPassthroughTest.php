<?php

declare(strict_types=1);

namespace Tests\Integration\MySql\Pdo\UnknownSchema;

use PDOException;
use Tests\Integration\MySqlIntegrationTestCase;
use ZtdQuery\Config\UnknownSchemaBehavior;
use ZtdQuery\Config\ZtdConfig;

/**
 * Tests for UnknownSchemaBehavior::Passthrough mode.
 *
 * In Passthrough mode, SELECT queries referencing unknown tables/columns are passed
 * through to MySQL, which will return an error for unknown schema.
 *
 * Note: DML operations (INSERT/UPDATE/DELETE) on unknown tables may behave differently
 * as ZTD needs schema information to process them.
 */
final class UnknownSchemaPassthroughTest extends MySqlIntegrationTestCase
{
    protected function getZtdConfig(): ZtdConfig
    {
        return new ZtdConfig(
            unknownSchemaBehavior: UnknownSchemaBehavior::Passthrough
        );
    }

    public function testSelectFromNonExistentTableThrowsPdoException(): void
    {
        $this->expectException(PDOException::class);
        $this->expectExceptionMessageMatches("/doesn't exist/");

        $this->ztdQuery('SELECT * FROM nonexistent_table_xyz123');
    }

    public function testSelectWithJoinToNonExistentTableThrowsPdoException(): void
    {
        $table = $this->uniqueTableName('users');
        $this->rawPdo->exec("CREATE TABLE `{$table}` (id INT PRIMARY KEY, name VARCHAR(255))");
        $this->rawPdo->exec("INSERT INTO `{$table}` (id, name) VALUES (1, 'Alice')");

        $this->expectException(PDOException::class);
        $this->expectExceptionMessageMatches("/doesn't exist/");

        $this->ztdQuery("SELECT * FROM `{$table}` JOIN nonexistent_table_xyz123 ON true");
    }

    public function testSelectExistingTableWorks(): void
    {
        $table = $this->uniqueTableName('users');
        $this->rawPdo->exec("CREATE TABLE `{$table}` (id INT PRIMARY KEY, name VARCHAR(255))");
        $this->rawPdo->exec("INSERT INTO `{$table}` (id, name) VALUES (1, 'Alice')");

        $rows = $this->ztdQuery("SELECT * FROM `{$table}`");

        $this->assertCount(1, $rows);
        $this->assertEquals('Alice', $rows[0]['name']);
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

    public function testSelectUnknownColumnThrowsPdoException(): void
    {
        $table = $this->uniqueTableName('users');
        $this->rawPdo->exec("CREATE TABLE `{$table}` (id INT PRIMARY KEY, name VARCHAR(255))");

        $this->expectException(PDOException::class);
        $this->expectExceptionMessageMatches("/Unknown column/");

        $this->ztdQuery("SELECT unknown_column FROM `{$table}`");
    }
}
