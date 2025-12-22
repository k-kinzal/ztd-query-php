<?php

declare(strict_types=1);

namespace Tests\Integration\MySql\Pdo\Drop;

use Tests\Integration\MySqlIntegrationTestCase;

final class DropTemporaryTableTest extends MySqlIntegrationTestCase
{
    public function testDropTemporaryTable(): void
    {
        $table = $this->uniqueTableName('temp_users');

        $this->ztdPdo->exec("CREATE TEMPORARY TABLE `{$table}` (id INT PRIMARY KEY, name VARCHAR(255))");
        $this->ztdPdo->exec("INSERT INTO `{$table}` VALUES (1, 'Alice')");

        $this->ztdPdo->exec("DROP TEMPORARY TABLE `{$table}`");

        $this->expectException(\RuntimeException::class);
        $this->ztdPdo->query("SELECT * FROM `{$table}`");
    }
}
