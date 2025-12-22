<?php

declare(strict_types=1);

namespace Tests\Integration\MySql\Pdo\AlterTable;

use Tests\Integration\MySqlIntegrationTestCase;

final class AlterTableAddColumnTest extends MySqlIntegrationTestCase
{
    public function testAlterTableAddColumnUpdatesSchema(): void
    {
        $table = $this->uniqueTableName('users');

        $this->rawPdo->exec("CREATE TABLE `{$table}` (id INT PRIMARY KEY, name VARCHAR(255))");
        $this->ztdPdo->exec("INSERT INTO `{$table}` (id, name) VALUES (1, 'Alice')");

        $this->ztdPdo->exec("ALTER TABLE `{$table}` ADD COLUMN email VARCHAR(255)");

        $this->ztdPdo->exec("INSERT INTO `{$table}` (id, name, email) VALUES (2, 'Bob', 'bob@example.com')");

        $ztdRows = $this->ztdQuery("SELECT * FROM `{$table}` WHERE id = 2");
        $this->assertCount(1, $ztdRows);
        $this->assertSame('bob@example.com', $ztdRows[0]['email']);
    }

    public function testAlterTableAddColumnWithExplicitValues(): void
    {
        $table = $this->uniqueTableName('users');

        $this->rawPdo->exec("CREATE TABLE `{$table}` (id INT PRIMARY KEY, name VARCHAR(255))");

        $this->ztdPdo->exec("ALTER TABLE `{$table}` ADD COLUMN status VARCHAR(50) DEFAULT 'active'");

        $this->ztdPdo->exec("INSERT INTO `{$table}` (id, name, status) VALUES (1, 'Alice', 'pending')");

        $ztdRows = $this->ztdQuery("SELECT * FROM `{$table}`");
        $this->assertSame('pending', $ztdRows[0]['status']);
    }
}
