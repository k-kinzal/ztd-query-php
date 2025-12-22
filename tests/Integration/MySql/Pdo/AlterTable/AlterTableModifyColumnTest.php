<?php

declare(strict_types=1);

namespace Tests\Integration\MySql\Pdo\AlterTable;

use Tests\Integration\MySqlIntegrationTestCase;

final class AlterTableModifyColumnTest extends MySqlIntegrationTestCase
{
    public function testAlterTableModifyColumnType(): void
    {
        $table = $this->uniqueTableName('users');

        $this->rawPdo->exec("CREATE TABLE `{$table}` (id INT PRIMARY KEY, name VARCHAR(50))");

        $this->ztdPdo->exec("ALTER TABLE `{$table}` MODIFY COLUMN name VARCHAR(255)");

        $this->ztdPdo->exec("INSERT INTO `{$table}` VALUES (1, '" . str_repeat('a', 100) . "')");

        $ztdRows = $this->ztdQuery("SELECT * FROM `{$table}`");
        $this->assertCount(1, $ztdRows);
        $this->assertIsString($ztdRows[0]['name']);
        $this->assertSame(100, strlen($ztdRows[0]['name']));
    }

    public function testAlterTableModifyColumnNullability(): void
    {
        $table = $this->uniqueTableName('users');

        $this->rawPdo->exec("CREATE TABLE `{$table}` (id INT PRIMARY KEY, name VARCHAR(255) NOT NULL)");

        $this->ztdPdo->exec("ALTER TABLE `{$table}` MODIFY COLUMN name VARCHAR(255) NULL");

        $this->ztdPdo->exec("INSERT INTO `{$table}` VALUES (1, NULL)");

        $ztdRows = $this->ztdQuery("SELECT * FROM `{$table}`");
        $this->assertCount(1, $ztdRows);
        $this->assertNull($ztdRows[0]['name']);
    }
}
