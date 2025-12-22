<?php

declare(strict_types=1);

namespace Tests\Integration\MySql\Pdo\Select;

use Tests\Integration\MySqlIntegrationTestCase;

final class SelectHavingTest extends MySqlIntegrationTestCase
{
    public function testSelectHavingFiltersAggregatedRows(): void
    {
        $table = $this->uniqueTableName('orders');

        $this->rawPdo->exec("CREATE TABLE `{$table}` (id INT PRIMARY KEY, category VARCHAR(50), amount INT)");
        $this->rawPdo->exec("INSERT INTO `{$table}` VALUES (1, 'A', 100), (2, 'A', 200), (3, 'B', 150)");

        $rows = $this->ztdQuery("SELECT category, SUM(amount) as total FROM `{$table}` GROUP BY category HAVING total > 200");

        $this->assertCount(1, $rows);
        $this->assertSame('A', $rows[0]['category']);
    }

    public function testSelectHavingWithZtdMatchesNonZtd(): void
    {
        $table = $this->uniqueTableName('orders');

        $this->rawPdo->exec("CREATE TABLE `{$table}` (id INT PRIMARY KEY, category VARCHAR(50), amount INT)");
        $this->rawPdo->exec("INSERT INTO `{$table}` VALUES (1, 'A', 100), (2, 'A', 200), (3, 'B', 150), (4, 'C', 50)");

        $rawRows = $this->rawQuery("SELECT category, SUM(amount) as total FROM `{$table}` GROUP BY category HAVING total >= 150 ORDER BY category");
        $ztdRows = $this->ztdQuery("SELECT category, SUM(amount) as total FROM `{$table}` GROUP BY category HAVING total >= 150 ORDER BY category");

        $this->assertSame($rawRows, $ztdRows);
    }
}
