<?php

declare(strict_types=1);

namespace Tests\Integration\MySql\Pdo\Update;

use Tests\Integration\MySqlIntegrationTestCase;

final class UpdateJoinTest extends MySqlIntegrationTestCase
{
    public function testUpdateWithJoin(): void
    {
        $users = $this->uniqueTableName('users');
        $orders = $this->uniqueTableName('orders');

        $this->rawPdo->exec("CREATE TABLE `{$users}` (id INT PRIMARY KEY, name VARCHAR(255), has_order TINYINT DEFAULT 0)");
        $this->rawPdo->exec("CREATE TABLE `{$orders}` (id INT PRIMARY KEY, user_id INT, status VARCHAR(50))");
        $this->ztdPdo->exec("INSERT INTO `{$users}` VALUES (1, 'Alice', 0), (2, 'Bob', 0)");
        $this->ztdPdo->exec("INSERT INTO `{$orders}` VALUES (1, 1, 'completed')");

        $this->ztdPdo->exec("UPDATE `{$users}` u INNER JOIN `{$orders}` o ON u.id = o.user_id SET u.has_order = 1 WHERE o.status = 'completed'");

        $ztdRows = $this->ztdQuery("SELECT * FROM `{$users}` ORDER BY id");
        $this->assertSame(1, $ztdRows[0]['has_order']); // Alice has order
        $this->assertSame(0, $ztdRows[1]['has_order']); // Bob does not
    }
}
