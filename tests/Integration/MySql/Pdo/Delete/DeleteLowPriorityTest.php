<?php

declare(strict_types=1);

namespace Tests\Integration\MySql\Pdo\Delete;

use Tests\Integration\MySqlIntegrationTestCase;

final class DeleteLowPriorityTest extends MySqlIntegrationTestCase
{
    public function testDeleteLowPriorityIsSupported(): void
    {
        $table = $this->uniqueTableName('users');

        $this->rawPdo->exec("CREATE TABLE `{$table}` (id INT PRIMARY KEY, name VARCHAR(255))");
        $this->ztdPdo->exec("INSERT INTO `{$table}` VALUES (1, 'Alice'), (2, 'Bob')");

        $affected = $this->ztdPdo->exec("DELETE LOW_PRIORITY FROM `{$table}` WHERE id = 1");

        $this->assertSame(1, $affected);

        $ztdRows = $this->ztdQuery("SELECT * FROM `{$table}`");
        $this->assertCount(1, $ztdRows);
        $this->assertSame('Bob', $ztdRows[0]['name']);
    }
}
