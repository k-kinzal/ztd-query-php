<?php

declare(strict_types=1);

namespace Tests\Integration\MySql\Pdo\Replace;

use Tests\Integration\MySqlIntegrationTestCase;

final class ReplaceLowPriorityTest extends MySqlIntegrationTestCase
{
    public function testReplaceLowPriorityIsSupported(): void
    {
        $table = $this->uniqueTableName('users');

        $this->rawPdo->exec("CREATE TABLE `{$table}` (id INT PRIMARY KEY, name VARCHAR(255))");

        $this->ztdPdo->exec("REPLACE LOW_PRIORITY INTO `{$table}` (id, name) VALUES (1, 'Alice')");

        $ztdRows = $this->ztdQuery("SELECT * FROM `{$table}`");
        $this->assertCount(1, $ztdRows);
    }
}
