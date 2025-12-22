<?php

declare(strict_types=1);

namespace Tests\Integration\MySql\Pdo\Error;

use Tests\Integration\MySqlIntegrationTestCase;
use ZtdQuery\Exception\UnsupportedSqlException;

/**
 * Tests for schema not found error handling.
 *
 * These tests document how ZTD handles DDL operations on non-existent tables.
 * Currently, most DDL operations throw UnsupportedSqlException as they are
 * not fully supported by the ZTD layer.
 */
final class SchemaNotFoundTest extends MySqlIntegrationTestCase
{
    public function testDropNonExistentTableThrowsUnsupportedSqlException(): void
    {
        // DROP TABLE on non-existent table without IF EXISTS
        // Currently throws UnsupportedSqlException as DDL is not fully supported
        $this->expectException(UnsupportedSqlException::class);

        $this->ztdPdo->exec('DROP TABLE nonexistent_table_xyz123');
    }

    public function testDropTableIfExistsDoesNotThrow(): void
    {
        // Should not throw - IF EXISTS handles non-existent table
        $result = $this->ztdPdo->exec('DROP TABLE IF EXISTS nonexistent_table_xyz123');

        $this->assertSame(0, $result);
    }

    public function testAlterNonExistentTableThrowsUnsupportedSqlException(): void
    {
        // ALTER TABLE on non-existent table
        // Currently throws UnsupportedSqlException as DDL is not fully supported
        $this->expectException(UnsupportedSqlException::class);

        $this->ztdPdo->exec('ALTER TABLE nonexistent_table_xyz123 ADD COLUMN foo INT');
    }

    public function testDropExistingTableSucceeds(): void
    {
        $table = $this->uniqueTableName('users');
        $this->rawPdo->exec("CREATE TABLE `{$table}` (id INT PRIMARY KEY)");

        // Create virtual table first to register it with ZTD
        $this->ztdPdo->exec("CREATE TABLE IF NOT EXISTS `{$table}` (id INT PRIMARY KEY)");

        // Drop should succeed
        $result = $this->ztdPdo->exec("DROP TABLE `{$table}`");

        $this->assertSame(0, $result);
    }

    public function testAlterExistingTableSucceeds(): void
    {
        $table = $this->uniqueTableName('users');
        $this->rawPdo->exec("CREATE TABLE `{$table}` (id INT PRIMARY KEY, name VARCHAR(255))");

        // ALTER should succeed on existing table
        $result = $this->ztdPdo->exec("ALTER TABLE `{$table}` ADD COLUMN age INT");

        $this->assertSame(0, $result);
    }

    public function testSelectFromNonExistentTableThrowsPdoException(): void
    {
        // SELECT from non-existent table is passed to MySQL which throws PDOException
        $this->expectException(\PDOException::class);
        $this->expectExceptionMessageMatches("/doesn't exist|Table.*doesn't exist/i");

        $this->ztdQuery("SELECT * FROM nonexistent_table_xyz123");
    }

    public function testDropExistingPhysicalTableSucceeds(): void
    {
        $table = $this->uniqueTableName('users');
        $this->rawPdo->exec("CREATE TABLE `{$table}` (id INT PRIMARY KEY)");

        // DROP TABLE on existing physical table should succeed
        // by registering it and marking as dropped
        $result = $this->ztdPdo->exec("DROP TABLE IF EXISTS `{$table}`");

        $this->assertSame(0, $result);
    }
}
