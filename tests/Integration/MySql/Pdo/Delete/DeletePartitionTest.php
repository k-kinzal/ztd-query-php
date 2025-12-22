<?php

declare(strict_types=1);

namespace Tests\Integration\MySql\Pdo\Delete;

use Tests\Integration\MySqlIntegrationTestCase;

final class DeletePartitionTest extends MySqlIntegrationTestCase
{
    public function testDeletePartitionThrowsDueToParserLimitation(): void
    {
        $table = $this->uniqueTableName('partitioned');

        $this->rawPdo->exec("CREATE TABLE `{$table}` (id INT PRIMARY KEY, name VARCHAR(255)) PARTITION BY HASH(id) PARTITIONS 4");
        $this->rawPdo->exec("INSERT INTO `{$table}` VALUES (1, 'Alice')");

        $this->expectException(\Throwable::class);
        $this->ztdPdo->exec("DELETE FROM `{$table}` PARTITION (p0) WHERE id = 1");
    }
}
