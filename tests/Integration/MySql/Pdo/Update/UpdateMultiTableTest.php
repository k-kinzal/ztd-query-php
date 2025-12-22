<?php

declare(strict_types=1);

namespace Tests\Integration\MySql\Pdo\Update;

use Tests\Integration\MySqlIntegrationTestCase;

final class UpdateMultiTableTest extends MySqlIntegrationTestCase
{
    public function testUpdateMultiTableModifiesPrimaryTable(): void
    {
        $users = $this->uniqueTableName('users');
        $profiles = $this->uniqueTableName('profiles');

        $this->rawPdo->exec("CREATE TABLE `{$users}` (id INT PRIMARY KEY, name VARCHAR(255), active TINYINT)");
        $this->rawPdo->exec("CREATE TABLE `{$profiles}` (user_id INT PRIMARY KEY, bio TEXT)");
        $this->ztdPdo->exec("INSERT INTO `{$users}` VALUES (1, 'Alice', 1)");
        $this->ztdPdo->exec("INSERT INTO `{$profiles}` VALUES (1, 'Hello')");

        $this->ztdPdo->exec("UPDATE `{$users}` u, `{$profiles}` p SET u.active = 0 WHERE u.id = p.user_id AND u.id = 1");

        $userRows = $this->ztdQuery("SELECT * FROM `{$users}` WHERE id = 1");

        $this->assertSame(0, $userRows[0]['active']);
    }
}
