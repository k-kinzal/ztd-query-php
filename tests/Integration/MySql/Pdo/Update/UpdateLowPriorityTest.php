<?php

declare(strict_types=1);

namespace Tests\Integration\MySql\Pdo\Update;

use Tests\Integration\MySqlIntegrationTestCase;

final class UpdateLowPriorityTest extends MySqlIntegrationTestCase
{
    public function testUpdateLowPriorityIsSupported(): void
    {
        $table = $this->uniqueTableName('users');

        $this->rawPdo->exec("CREATE TABLE `{$table}` (id INT PRIMARY KEY, name VARCHAR(255))");
        $this->ztdPdo->exec("INSERT INTO `{$table}` VALUES (1, 'Alice')");

        $this->ztdPdo->exec("UPDATE LOW_PRIORITY `{$table}` SET name = 'Bob' WHERE id = 1");

        $ztdRows = $this->ztdQuery("SELECT * FROM `{$table}` WHERE id = 1");
        $this->assertSame('Bob', $ztdRows[0]['name']);
    }
}
