<?php

declare(strict_types=1);

namespace Tests\Integration\MySql\Pdo\AlterTable;

use Tests\Integration\MySqlIntegrationTestCase;

final class AlterTableRenameColumnTest extends MySqlIntegrationTestCase
{
    public function testAlterTableRenameColumn(): void
    {
        $table = $this->uniqueTableName('users');

        $this->rawPdo->exec("CREATE TABLE `{$table}` (id INT PRIMARY KEY, name VARCHAR(255))");
        $this->ztdPdo->exec("INSERT INTO `{$table}` (id, name) VALUES (1, 'Alice')");

        $this->ztdPdo->exec("ALTER TABLE `{$table}` RENAME COLUMN name TO full_name");

        $ztdRows = $this->ztdQuery("SELECT * FROM `{$table}`");
        $this->assertCount(1, $ztdRows);
        $this->assertArrayHasKey('full_name', $ztdRows[0]);
        $this->assertSame('Alice', $ztdRows[0]['full_name']);
    }
}
