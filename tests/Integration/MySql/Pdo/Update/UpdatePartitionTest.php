<?php

declare(strict_types=1);

namespace Tests\Integration\MySql\Pdo\Update;

use Tests\Integration\MySqlIntegrationTestCase;

final class UpdatePartitionTest extends MySqlIntegrationTestCase
{
    public function testUpdatePartitionThrowsDueToParserLimitation(): void
    {
        $table = $this->uniqueTableName('partitioned');

        $this->rawPdo->exec("CREATE TABLE `{$table}` (id INT PRIMARY KEY, name VARCHAR(255)) ENGINE=InnoDB PARTITION BY HASH(id) PARTITIONS 4");
        $this->ztdPdo->exec("INSERT INTO `{$table}` VALUES (1, 'Alice')");

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('ZTD Write Protection');
        $this->ztdPdo->exec("UPDATE `{$table}` PARTITION (p0) SET name = 'Bob' WHERE id = 1");
    }
}
