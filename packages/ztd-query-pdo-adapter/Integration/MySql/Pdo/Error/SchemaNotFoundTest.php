<?php

declare(strict_types=1);

namespace Tests\Integration\MySql\Pdo\Error;

use Tests\Support\MySqlIntegrationTestCase;
use ZtdQuery\Adapter\Pdo\ZtdPdoException;

/**
 * Tests for schema not found error handling.
 *
 * These tests document how ZTD handles DDL operations on non-existent tables.
 * DDL operations on non-existent tables are classified as UnknownSchemaException
 * and surfaced as ZtdPdoException through the adapter boundary.
 */
final class SchemaNotFoundTest extends MySqlIntegrationTestCase
{
    public function testDropNonExistentTableThrowsZtdPdoException(): void
    {
        $this->expectException(ZtdPdoException::class);

        $this->ztdPdo->exec('DROP TABLE nonexistent_table_xyz123');
    }

    public function testDropTableIfExistsDoesNotThrow(): void
    {
        $result = $this->ztdPdo->exec('DROP TABLE IF EXISTS nonexistent_table_xyz123');

        $this->assertSame(0, $result);
    }

    public function testAlterNonExistentTableThrowsZtdPdoException(): void
    {
        $this->expectException(ZtdPdoException::class);

        $this->ztdPdo->exec('ALTER TABLE nonexistent_table_xyz123 ADD COLUMN foo INT');
    }

    public function testDropExistingTableSucceeds(): void
    {
        $table = $this->uniqueTableName('users');
        $this->rawPdo->exec("CREATE TABLE `{$table}` (id INT PRIMARY KEY)");

        $this->ztdPdo->exec("CREATE TABLE IF NOT EXISTS `{$table}` (id INT PRIMARY KEY)");

        $result = $this->ztdPdo->exec("DROP TABLE `{$table}`");

        $this->assertSame(0, $result);
    }

    public function testAlterExistingTableSucceeds(): void
    {
        $table = $this->uniqueTableName('users');
        $this->rawPdo->exec("CREATE TABLE `{$table}` (id INT PRIMARY KEY, name VARCHAR(255))");

        $result = $this->ztdPdo->exec("ALTER TABLE `{$table}` ADD COLUMN age INT");

        $this->assertSame(0, $result);
    }

    public function testSelectFromNonExistentTableThrowsPdoException(): void
    {
        $this->expectException(\PDOException::class);
        $this->expectExceptionMessageMatches("/doesn't exist|Table.*doesn't exist/i");

        $this->ztdQuery("SELECT * FROM nonexistent_table_xyz123");
    }

    public function testDropExistingPhysicalTableSucceeds(): void
    {
        $table = $this->uniqueTableName('users');
        $this->rawPdo->exec("CREATE TABLE `{$table}` (id INT PRIMARY KEY)");

        $result = $this->ztdPdo->exec("DROP TABLE IF EXISTS `{$table}`");

        $this->assertSame(0, $result);
    }
}
