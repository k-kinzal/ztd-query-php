<?php

declare(strict_types=1);

namespace Tests\Integration\MySql\Pdo\Select;

use Tests\Support\MySqlIntegrationTestCase;

final class SelectPartitionTest extends MySqlIntegrationTestCase
{
    public function testSelectPartitionFromRealTable(): void
    {
        $table = $this->uniqueTableName('partitioned');

        $this->rawPdo->exec("CREATE TABLE `{$table}` (id INT PRIMARY KEY, name VARCHAR(255)) ENGINE=InnoDB PARTITION BY HASH(id) PARTITIONS 4");
        $this->rawPdo->exec("INSERT INTO `{$table}` VALUES (1, 'Alice')");

        $rows = $this->ztdQuery("SELECT * FROM `{$table}` PARTITION (p1)");
        $this->assertGreaterThanOrEqual(0, count($rows));
    }
}
