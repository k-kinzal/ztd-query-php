<?php

declare(strict_types=1);

namespace Tests\Integration\MySql\Pdo\Select;

use Tests\Integration\MySqlIntegrationTestCase;

final class SelectLockInShareModeTest extends MySqlIntegrationTestCase
{
    public function testSelectLockInShareModeIgnoredInZtdMode(): void
    {
        $table = $this->uniqueTableName('users');

        $this->rawPdo->exec("CREATE TABLE `{$table}` (id INT PRIMARY KEY, name VARCHAR(255)) ENGINE=InnoDB");
        $this->rawPdo->exec("INSERT INTO `{$table}` VALUES (1, 'Alice'), (2, 'Bob')");

        $rows = $this->ztdQuery("SELECT * FROM `{$table}` LOCK IN SHARE MODE");

        $this->assertCount(2, $rows);
    }

    public function testSelectLockInShareModeWithZtdMatchesNonZtd(): void
    {
        $table = $this->uniqueTableName('users');

        $this->rawPdo->exec("CREATE TABLE `{$table}` (id INT PRIMARY KEY, name VARCHAR(255)) ENGINE=InnoDB");
        $this->rawPdo->exec("INSERT INTO `{$table}` VALUES (1, 'Alice'), (2, 'Bob')");

        $rawRows = $this->rawQuery("SELECT * FROM `{$table}` ORDER BY id LOCK IN SHARE MODE");
        $ztdRows = $this->ztdQuery("SELECT * FROM `{$table}` ORDER BY id LOCK IN SHARE MODE");

        $this->assertSame($rawRows, $ztdRows);
    }
}
