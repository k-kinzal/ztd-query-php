<?php

declare(strict_types=1);

namespace Tests\Integration\MySql\Pdo\Insert;

use Tests\Integration\MySqlIntegrationTestCase;

final class InsertIgnoreTest extends MySqlIntegrationTestCase
{
    public function testInsertIgnoreSkipsDuplicates(): void
    {
        $table = $this->uniqueTableName('users');

        $this->rawPdo->exec("CREATE TABLE `{$table}` (id INT PRIMARY KEY, name VARCHAR(255))");
        $this->ztdPdo->exec("INSERT INTO `{$table}` VALUES (1, 'Alice')");

        $this->ztdPdo->exec("INSERT IGNORE INTO `{$table}` (id, name) VALUES (1, 'Bob'), (2, 'Charlie')");

        $ztdRows = $this->ztdQuery("SELECT * FROM `{$table}` ORDER BY id");
        $this->assertCount(2, $ztdRows);
        $this->assertSame('Alice', $ztdRows[0]['name']); // Original row unchanged
        $this->assertSame('Charlie', $ztdRows[1]['name']); // New row inserted
    }
}
