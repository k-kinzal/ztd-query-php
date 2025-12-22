<?php

declare(strict_types=1);

namespace Tests\Integration\MySql\Pdo\CreateTable;

use Tests\Integration\MySqlIntegrationTestCase;

final class CreateTableIfNotExistsTest extends MySqlIntegrationTestCase
{
    public function testCreateTableIfNotExistsDoesNotErrorOnExisting(): void
    {
        $table = $this->uniqueTableName('users');

        $this->rawPdo->exec("CREATE TABLE `{$table}` (id INT PRIMARY KEY, name VARCHAR(255))");

        $this->ztdPdo->exec("CREATE TABLE IF NOT EXISTS `{$table}` (id INT PRIMARY KEY, name VARCHAR(255))");

        $this->rawPdo->exec("INSERT INTO `{$table}` VALUES (1, 'Alice')");
        $ztdRows = $this->ztdQuery("SELECT * FROM `{$table}`");
        $this->assertCount(1, $ztdRows);
    }

    public function testCreateTableIfNotExistsCreatesNewTable(): void
    {
        $table = $this->uniqueTableName('users');

        $this->ztdPdo->exec("CREATE TABLE IF NOT EXISTS `{$table}` (id INT PRIMARY KEY, name VARCHAR(255))");

        $this->ztdPdo->exec("INSERT INTO `{$table}` VALUES (1, 'Alice')");
        $ztdRows = $this->ztdQuery("SELECT * FROM `{$table}`");
        $this->assertCount(1, $ztdRows);
    }
}
