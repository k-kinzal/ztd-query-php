<?php

declare(strict_types=1);

namespace Tests\Integration\MySql\Pdo\Select;

use Tests\Integration\MySqlIntegrationTestCase;

final class SelectGroupByTest extends MySqlIntegrationTestCase
{
    public function testSelectGroupByAggregatesRows(): void
    {
        $table = $this->uniqueTableName('orders');

        $this->rawPdo->exec("CREATE TABLE `{$table}` (id INT PRIMARY KEY, category VARCHAR(50), amount INT)");
        $this->rawPdo->exec("INSERT INTO `{$table}` VALUES (1, 'A', 100), (2, 'A', 200), (3, 'B', 150)");

        $rows = $this->ztdQuery("SELECT category, SUM(amount) as total FROM `{$table}` GROUP BY category ORDER BY category");

        $this->assertCount(2, $rows);
    }

    public function testSelectGroupByWithZtdMatchesNonZtd(): void
    {
        $table = $this->uniqueTableName('orders');

        $this->rawPdo->exec("CREATE TABLE `{$table}` (id INT PRIMARY KEY, category VARCHAR(50), amount INT)");
        $this->rawPdo->exec("INSERT INTO `{$table}` VALUES (1, 'A', 100), (2, 'A', 200), (3, 'B', 150)");

        $rawRows = $this->rawQuery("SELECT category, SUM(amount) as total FROM `{$table}` GROUP BY category ORDER BY category");
        $ztdRows = $this->ztdQuery("SELECT category, SUM(amount) as total FROM `{$table}` GROUP BY category ORDER BY category");

        $this->assertSame($rawRows, $ztdRows);
    }

    public function testSelectGroupByWithCountAggregate(): void
    {
        $table = $this->uniqueTableName('orders');

        $this->rawPdo->exec("CREATE TABLE `{$table}` (id INT PRIMARY KEY, category VARCHAR(50), amount INT)");
        $this->rawPdo->exec("INSERT INTO `{$table}` VALUES (1, 'A', 100), (2, 'A', 200), (3, 'B', 150)");

        $rawRows = $this->rawQuery("SELECT category, COUNT(*) as cnt FROM `{$table}` GROUP BY category ORDER BY category");
        $ztdRows = $this->ztdQuery("SELECT category, COUNT(*) as cnt FROM `{$table}` GROUP BY category ORDER BY category");

        $this->assertSame($rawRows, $ztdRows);
    }
}
