<?php

declare(strict_types=1);

namespace Tests\Integration\MySql\Pdo\AlterTable;

use Tests\Integration\MySqlIntegrationTestCase;

final class AlterTableAddMultiColumnTest extends MySqlIntegrationTestCase
{
    public function testAlterTableAddMultipleColumns(): void
    {
        $table = $this->uniqueTableName('users');

        $this->rawPdo->exec("CREATE TABLE `{$table}` (id INT PRIMARY KEY)");

        $this->ztdPdo->exec("ALTER TABLE `{$table}` ADD COLUMN name VARCHAR(255), ADD COLUMN email VARCHAR(255)");

        $this->ztdPdo->exec("INSERT INTO `{$table}` (id, name, email) VALUES (1, 'Alice', 'alice@example.com')");

        $ztdRows = $this->ztdQuery("SELECT * FROM `{$table}`");
        $this->assertCount(1, $ztdRows);
        $this->assertSame('Alice', $ztdRows[0]['name']);
        $this->assertSame('alice@example.com', $ztdRows[0]['email']);
    }
}
