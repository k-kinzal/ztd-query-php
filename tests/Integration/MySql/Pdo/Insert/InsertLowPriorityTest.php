<?php

declare(strict_types=1);

namespace Tests\Integration\MySql\Pdo\Insert;

use Tests\Integration\MySqlIntegrationTestCase;

final class InsertLowPriorityTest extends MySqlIntegrationTestCase
{
    public function testInsertLowPriorityIsSupported(): void
    {
        $table = $this->uniqueTableName('users');

        $this->rawPdo->exec("CREATE TABLE `{$table}` (id INT PRIMARY KEY, name VARCHAR(255))");

        $this->ztdPdo->exec("INSERT LOW_PRIORITY INTO `{$table}` (id, name) VALUES (1, 'Alice')");

        $ztdRows = $this->ztdQuery("SELECT * FROM `{$table}`");
        $this->assertCount(1, $ztdRows);
    }
}
