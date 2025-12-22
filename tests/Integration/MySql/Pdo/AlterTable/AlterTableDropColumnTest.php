<?php

declare(strict_types=1);

namespace Tests\Integration\MySql\Pdo\AlterTable;

use Tests\Integration\MySqlIntegrationTestCase;

final class AlterTableDropColumnTest extends MySqlIntegrationTestCase
{
    public function testAlterTableDropColumnRemovesFromSchema(): void
    {
        $table = $this->uniqueTableName('users');

        $this->rawPdo->exec("CREATE TABLE `{$table}` (id INT PRIMARY KEY, name VARCHAR(255), email VARCHAR(255))");
        $this->ztdPdo->exec("INSERT INTO `{$table}` VALUES (1, 'Alice', 'alice@example.com')");

        $this->ztdPdo->exec("ALTER TABLE `{$table}` DROP COLUMN email");

        $ztdRows = $this->ztdQuery("SELECT * FROM `{$table}`");
        $this->assertCount(1, $ztdRows);
        $this->assertArrayNotHasKey('email', $ztdRows[0]);
    }
}
