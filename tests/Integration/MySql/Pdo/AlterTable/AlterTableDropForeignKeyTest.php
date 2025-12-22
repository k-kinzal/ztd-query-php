<?php

declare(strict_types=1);

namespace Tests\Integration\MySql\Pdo\AlterTable;

use Tests\Integration\MySqlIntegrationTestCase;

final class AlterTableDropForeignKeyTest extends MySqlIntegrationTestCase
{
    public function testAlterTableDropForeignKey(): void
    {
        $usersTable = $this->uniqueTableName('users');
        $ordersTable = $this->uniqueTableName('orders');

        $this->rawPdo->exec("CREATE TABLE `{$usersTable}` (id INT PRIMARY KEY)");
        $this->rawPdo->exec("CREATE TABLE `{$ordersTable}` (id INT PRIMARY KEY, user_id INT, CONSTRAINT fk_user FOREIGN KEY (user_id) REFERENCES `{$usersTable}` (id))");

        $this->ztdPdo->exec("ALTER TABLE `{$ordersTable}` DROP FOREIGN KEY fk_user");

        $this->ztdPdo->exec("INSERT INTO `{$ordersTable}` (id, user_id) VALUES (1, 999)");

        $ztdRows = $this->ztdQuery("SELECT * FROM `{$ordersTable}`");
        $this->assertCount(1, $ztdRows);
    }
}
