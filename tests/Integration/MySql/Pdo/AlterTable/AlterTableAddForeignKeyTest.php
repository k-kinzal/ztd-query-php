<?php

declare(strict_types=1);

namespace Tests\Integration\MySql\Pdo\AlterTable;

use Tests\Integration\MySqlIntegrationTestCase;

final class AlterTableAddForeignKeyTest extends MySqlIntegrationTestCase
{
    public function testAlterTableAddForeignKey(): void
    {
        $users = $this->uniqueTableName('users');
        $orders = $this->uniqueTableName('orders');

        $this->rawPdo->exec("CREATE TABLE `{$users}` (id INT PRIMARY KEY, name VARCHAR(255))");
        $this->rawPdo->exec("CREATE TABLE `{$orders}` (id INT PRIMARY KEY, user_id INT)");

        $this->ztdPdo->exec("ALTER TABLE `{$orders}` ADD FOREIGN KEY (user_id) REFERENCES `{$users}`(id)");

        $this->ztdPdo->exec("INSERT INTO `{$users}` VALUES (1, 'Alice')");
        $this->ztdPdo->exec("INSERT INTO `{$orders}` VALUES (1, 1)");

        $ztdRows = $this->ztdQuery("SELECT * FROM `{$orders}`");
        $this->assertCount(1, $ztdRows);
    }
}
