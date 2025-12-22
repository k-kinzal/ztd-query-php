<?php

declare(strict_types=1);

namespace Tests\Integration\MySql\Pdo\AlterTable;

use Tests\Integration\MySqlIntegrationTestCase;

final class AlterTableDropPrimaryKeyTest extends MySqlIntegrationTestCase
{
    public function testAlterTableDropPrimaryKey(): void
    {
        $table = $this->uniqueTableName('users');

        $this->rawPdo->exec("CREATE TABLE `{$table}` (id INT PRIMARY KEY, name VARCHAR(255))");

        $this->ztdPdo->exec("ALTER TABLE `{$table}` DROP PRIMARY KEY");

        $this->ztdPdo->exec("INSERT INTO `{$table}` VALUES (1, 'Alice')");
        $this->ztdPdo->exec("INSERT INTO `{$table}` VALUES (1, 'Bob')");

        $ztdRows = $this->ztdQuery("SELECT * FROM `{$table}` ORDER BY name");
        $this->assertCount(2, $ztdRows);
    }
}
