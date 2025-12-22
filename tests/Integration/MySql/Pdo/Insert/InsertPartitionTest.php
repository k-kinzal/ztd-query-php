<?php

declare(strict_types=1);

namespace Tests\Integration\MySql\Pdo\Insert;

use Tests\Integration\MySqlIntegrationTestCase;

final class InsertPartitionTest extends MySqlIntegrationTestCase
{
    public function testInsertPartitionThrowsDueToParserLimitation(): void
    {
        $table = $this->uniqueTableName('partitioned');

        $this->rawPdo->exec("CREATE TABLE `{$table}` (id INT PRIMARY KEY, name VARCHAR(255)) PARTITION BY HASH(id) PARTITIONS 4");

        $this->expectException(\Throwable::class);
        $this->ztdPdo->exec("INSERT INTO `{$table}` PARTITION (p0) (id, name) VALUES (1, 'Alice')");
    }
}
