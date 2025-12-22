<?php

declare(strict_types=1);

namespace Tests\Integration\MySql\Pdo\CreateTable;

use Tests\Integration\MySqlIntegrationTestCase;

final class CreateTableBasicTest extends MySqlIntegrationTestCase
{
    public function testCreateTableCreatesVirtualTable(): void
    {
        $table = $this->uniqueTableName('users');

        $this->ztdPdo->exec("CREATE TABLE `{$table}` (id INT PRIMARY KEY, name VARCHAR(255))");

        $this->ztdPdo->exec("INSERT INTO `{$table}` VALUES (1, 'Alice')");
        $ztdRows = $this->ztdQuery("SELECT * FROM `{$table}`");
        $this->assertCount(1, $ztdRows);
        $this->assertSame('Alice', $ztdRows[0]['name']);

        $rows = $this->rawQuery("SHOW TABLES LIKE '{$table}'");
        $this->assertCount(0, $rows);
    }

    public function testCreateTableWithMultipleColumns(): void
    {
        $table = $this->uniqueTableName('products');

        $this->ztdPdo->exec("CREATE TABLE `{$table}` (
            id INT PRIMARY KEY AUTO_INCREMENT,
            name VARCHAR(255) NOT NULL,
            price DECIMAL(10,2) DEFAULT 0.00,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");

        $this->ztdPdo->exec("INSERT INTO `{$table}` (name, price) VALUES ('Widget', 29.99)");

        $ztdRows = $this->ztdQuery("SELECT * FROM `{$table}`");
        $this->assertCount(1, $ztdRows);
        $this->assertSame('Widget', $ztdRows[0]['name']);
        $this->assertSame('29.99', $ztdRows[0]['price']);
    }
}
