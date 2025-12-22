<?php

declare(strict_types=1);

namespace Tests\Integration\MySql\Pdo\AlterTable;

use Tests\Integration\MySqlIntegrationTestCase;

final class AlterTableRenameToTest extends MySqlIntegrationTestCase
{
    public function testAlterTableRenameTo(): void
    {
        $oldTable = $this->uniqueTableName('old_users');
        $newTable = $this->uniqueTableName('new_users');

        $this->rawPdo->exec("CREATE TABLE `{$oldTable}` (id INT PRIMARY KEY, name VARCHAR(255))");
        $this->ztdPdo->exec("INSERT INTO `{$oldTable}` (id, name) VALUES (1, 'Alice')");

        $this->ztdPdo->exec("ALTER TABLE `{$oldTable}` RENAME TO `{$newTable}`");

        $ztdRows = $this->ztdQuery("SELECT * FROM `{$newTable}`");
        $this->assertCount(1, $ztdRows);
        $this->assertSame('Alice', $ztdRows[0]['name']);
    }
}
