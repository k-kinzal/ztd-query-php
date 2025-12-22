<?php

declare(strict_types=1);

namespace Tests\Integration\MySql\Pdo\CreateTable;

use Tests\Integration\MySqlIntegrationTestCase;

final class CreateTemporaryTableTest extends MySqlIntegrationTestCase
{
    public function testCreateTemporaryTableCreatesVirtualTable(): void
    {
        $table = $this->uniqueTableName('temp_users');

        $this->ztdPdo->exec("CREATE TEMPORARY TABLE `{$table}` (id INT PRIMARY KEY, name VARCHAR(255))");

        $this->ztdPdo->exec("INSERT INTO `{$table}` VALUES (1, 'Alice')");

        $ztdRows = $this->ztdQuery("SELECT * FROM `{$table}`");
        $this->assertCount(1, $ztdRows);
        $this->assertSame('Alice', $ztdRows[0]['name']);
    }

    public function testCreateTemporaryTableIfNotExists(): void
    {
        $table = $this->uniqueTableName('temp_users');

        $this->ztdPdo->exec("CREATE TEMPORARY TABLE IF NOT EXISTS `{$table}` (id INT PRIMARY KEY, name VARCHAR(255))");
        $this->ztdPdo->exec("CREATE TEMPORARY TABLE IF NOT EXISTS `{$table}` (id INT PRIMARY KEY, name VARCHAR(255))");

        $this->ztdPdo->exec("INSERT INTO `{$table}` VALUES (1, 'Alice')");
        $ztdRows = $this->ztdQuery("SELECT * FROM `{$table}`");
        $this->assertCount(1, $ztdRows);
    }
}
