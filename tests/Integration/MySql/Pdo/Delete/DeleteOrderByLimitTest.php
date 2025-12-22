<?php

declare(strict_types=1);

namespace Tests\Integration\MySql\Pdo\Delete;

use Tests\Integration\MySqlIntegrationTestCase;

final class DeleteOrderByLimitTest extends MySqlIntegrationTestCase
{
    public function testDeleteOrderByLimitRemovesSpecificRows(): void
    {
        $table = $this->uniqueTableName('users');

        $this->rawPdo->exec("CREATE TABLE `{$table}` (id INT PRIMARY KEY, name VARCHAR(255), score INT)");
        $this->ztdPdo->exec("INSERT INTO `{$table}` VALUES (1, 'Alice', 100), (2, 'Bob', 50), (3, 'Charlie', 75)");

        $affected = $this->ztdPdo->exec("DELETE FROM `{$table}` ORDER BY score ASC LIMIT 1");

        $this->assertSame(1, $affected);

        $ztdRows = $this->ztdQuery("SELECT * FROM `{$table}` ORDER BY id");
        $this->assertCount(2, $ztdRows);
        $this->assertSame('Alice', $ztdRows[0]['name']);
        $this->assertSame('Charlie', $ztdRows[1]['name']);
    }

    public function testDeleteOrderByLimitMultipleRows(): void
    {
        $table = $this->uniqueTableName('users');

        $this->rawPdo->exec("CREATE TABLE `{$table}` (id INT PRIMARY KEY, name VARCHAR(255), created_at DATETIME)");
        $this->ztdPdo->exec("INSERT INTO `{$table}` VALUES (1, 'Alice', '2024-01-01'), (2, 'Bob', '2024-01-02'), (3, 'Charlie', '2024-01-03'), (4, 'Diana', '2024-01-04')");

        $affected = $this->ztdPdo->exec("DELETE FROM `{$table}` ORDER BY created_at ASC LIMIT 2");

        $this->assertSame(2, $affected);

        $ztdRows = $this->ztdQuery("SELECT * FROM `{$table}` ORDER BY id");
        $this->assertCount(2, $ztdRows);
        $this->assertSame('Charlie', $ztdRows[0]['name']);
        $this->assertSame('Diana', $ztdRows[1]['name']);
    }
}
