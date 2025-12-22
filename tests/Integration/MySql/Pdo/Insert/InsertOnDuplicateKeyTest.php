<?php

declare(strict_types=1);

namespace Tests\Integration\MySql\Pdo\Insert;

use Tests\Integration\MySqlIntegrationTestCase;

final class InsertOnDuplicateKeyTest extends MySqlIntegrationTestCase
{
    public function testInsertOnDuplicateKeyInsertsNewRow(): void
    {
        $table = $this->uniqueTableName('users');

        $this->rawPdo->exec("CREATE TABLE `{$table}` (id INT PRIMARY KEY, name VARCHAR(255), count INT)");

        $this->ztdPdo->exec("INSERT INTO `{$table}` (id, name, count) VALUES (1, 'Alice', 1) ON DUPLICATE KEY UPDATE count = count + 1");

        $ztdRows = $this->ztdQuery("SELECT * FROM `{$table}`");
        $this->assertCount(1, $ztdRows);
        $this->assertSame(1, $ztdRows[0]['count']);
    }

    public function testInsertOnDuplicateKeyUpdatesExistingRow(): void
    {
        $table = $this->uniqueTableName('users');

        $this->rawPdo->exec("CREATE TABLE `{$table}` (id INT PRIMARY KEY, name VARCHAR(255), status VARCHAR(50))");
        $this->ztdPdo->exec("INSERT INTO `{$table}` VALUES (1, 'Alice', 'active')");

        $this->ztdPdo->exec("INSERT INTO `{$table}` (id, name, status) VALUES (1, 'Alice', 'pending') ON DUPLICATE KEY UPDATE status = 'updated'");

        $ztdRows = $this->ztdQuery("SELECT * FROM `{$table}` WHERE id = 1");
        $this->assertCount(1, $ztdRows);
        $this->assertSame('updated', $ztdRows[0]['status']);
    }
}
