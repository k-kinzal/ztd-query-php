<?php

declare(strict_types=1);

namespace Tests\Integration\MySql\Pdo\UnknownSchema;

use Tests\Integration\MySqlIntegrationTestCase;
use ZtdQuery\Config\UnknownSchemaBehavior;
use ZtdQuery\Config\ZtdConfig;
use ZtdQuery\Exception\UnknownSchemaException;

/**
 * Tests for UnknownSchemaBehavior::Exception mode.
 *
 * In Exception mode, queries referencing non-existent tables throw
 * UnknownSchemaException from the ZTD layer before reaching MySQL.
 */
final class UnknownSchemaExceptionTest extends MySqlIntegrationTestCase
{
    protected function getZtdConfig(): ZtdConfig
    {
        return new ZtdConfig(
            unknownSchemaBehavior: UnknownSchemaBehavior::Exception
        );
    }

    public function testSelectFromNonExistentTableThrowsUnknownSchemaException(): void
    {
        // ZTD throws UnknownSchemaException before query reaches MySQL
        $this->expectException(UnknownSchemaException::class);
        $this->expectExceptionMessageMatches('/Unknown table/');

        $this->ztdQuery('SELECT * FROM nonexistent_table_xyz123');
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

    public function testPreparedStatementWithExistingTableWorks(): void
    {
        $table = $this->uniqueTableName('users');
        $this->rawPdo->exec("CREATE TABLE `{$table}` (id INT PRIMARY KEY, name VARCHAR(255))");
        $this->rawPdo->exec("INSERT INTO `{$table}` (id, name) VALUES (1, 'Alice')");

        $stmt = $this->ztdPdo->prepare("SELECT * FROM `{$table}` WHERE id = ?");
        $this->assertNotFalse($stmt);
        $stmt->execute([1]);
        /** @var array<int, array{name: string}> $rows */
        $rows = $stmt->fetchAll();

        $this->assertCount(1, $rows);
        $this->assertEquals('Alice', $rows[0]['name']);
    }

    public function testJoinWithNonExistentTableThrowsUnknownSchemaException(): void
    {
        $table = $this->uniqueTableName('users');
        $this->rawPdo->exec("CREATE TABLE `{$table}` (id INT PRIMARY KEY, name VARCHAR(255))");

        // ZTD throws UnknownSchemaException before query reaches MySQL
        $this->expectException(UnknownSchemaException::class);
        $this->expectExceptionMessageMatches('/Unknown table/');

        $this->ztdQuery("SELECT * FROM `{$table}` JOIN nonexistent_table ON true");
    }
}
