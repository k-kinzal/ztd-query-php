<?php

declare(strict_types=1);

namespace Tests\Integration\MySql\Pdo\Select;

use Tests\Integration\MySqlIntegrationTestCase;

final class SelectRecursiveCteTest extends MySqlIntegrationTestCase
{
    public function testSelectWithRecursiveCte(): void
    {
        $table = $this->uniqueTableName('categories');

        $this->rawPdo->exec("CREATE TABLE `{$table}` (id INT PRIMARY KEY, name VARCHAR(255), parent_id INT)");
        $this->rawPdo->exec("INSERT INTO `{$table}` VALUES (1, 'Root', NULL), (2, 'Child1', 1), (3, 'Child2', 1), (4, 'GrandChild', 2)");

        $sql = "WITH RECURSIVE cte AS (
            SELECT id, name, parent_id, 0 as level FROM `{$table}` WHERE parent_id IS NULL
            UNION ALL
            SELECT c.id, c.name, c.parent_id, cte.level + 1 FROM `{$table}` c JOIN cte ON c.parent_id = cte.id
        ) SELECT * FROM cte ORDER BY id";

        $rawRows = $this->rawQuery($sql);
        $ztdRows = $this->ztdQuery($sql);

        $this->assertSame($rawRows, $ztdRows);
        $this->assertCount(4, $ztdRows);
    }
}
