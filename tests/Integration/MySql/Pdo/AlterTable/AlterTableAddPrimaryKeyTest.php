<?php

declare(strict_types=1);

namespace Tests\Integration\MySql\Pdo\AlterTable;

use Tests\Integration\MySqlIntegrationTestCase;

final class AlterTableAddPrimaryKeyTest extends MySqlIntegrationTestCase
{
    public function testAlterTableAddPrimaryKey(): void
    {
        $table = $this->uniqueTableName('users');

        $this->rawPdo->exec("CREATE TABLE `{$table}` (id INT NOT NULL, name VARCHAR(255))");

        $this->ztdPdo->exec("ALTER TABLE `{$table}` ADD PRIMARY KEY (id)");

        $this->ztdPdo->exec("INSERT INTO `{$table}` VALUES (1, 'Alice')");

        $ztdRows = $this->ztdQuery("SELECT * FROM `{$table}`");
        $this->assertCount(1, $ztdRows);
    }
}
