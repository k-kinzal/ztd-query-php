<?php

declare(strict_types=1);

namespace Tests\Integration\MySql\Pdo\Update;

use Tests\Integration\MySqlIntegrationTestCase;

final class UpdateOrderByLimitTest extends MySqlIntegrationTestCase
{
    public function testUpdateWithOrderByAndLimitModifiesLimitedRows(): void
    {
        $table = $this->uniqueTableName('users');

        $this->rawPdo->exec("CREATE TABLE `{$table}` (id INT PRIMARY KEY, name VARCHAR(255), score INT)");
        $this->ztdPdo->exec("INSERT INTO `{$table}` VALUES (1, 'Alice', 80), (2, 'Bob', 90), (3, 'Charlie', 70)");

        $affected = $this->ztdPdo->exec("UPDATE `{$table}` SET score = score + 10 ORDER BY score DESC LIMIT 2");

        $this->assertSame(2, $affected);

        $ztdRows = $this->ztdQuery("SELECT * FROM `{$table}` ORDER BY id");
        $this->assertSame(90, $ztdRows[0]['score']); // Alice: 80 + 10
        $this->assertSame(100, $ztdRows[1]['score']); // Bob: 90 + 10
        $this->assertSame(70, $ztdRows[2]['score']); // Charlie: unchanged
    }
}
