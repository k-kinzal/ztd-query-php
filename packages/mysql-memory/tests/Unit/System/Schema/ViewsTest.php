<?php

declare(strict_types=1);

namespace Tests\Unit\System\Schema;

use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use MySqlMemory\System\Schema\Views;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(Views::class)]
#[Small]
final class ViewsTest extends TestCase
{
    public function testRowsListsTheViewsWithTheirDefinitions(): void
    {
        $s = (new Instance())->connect();
        $s->query('CREATE DATABASE d');
        $s->query('USE d');
        $s->query('CREATE TABLE p (id INT, name VARCHAR(5))');
        $s->query('CREATE VIEW v AS SELECT id, name FROM p WHERE id > 1 WITH CHECK OPTION');
        $s->query('CREATE VIEW w AS SELECT COUNT(*) FROM p');

        $result1 = $s->query("SELECT TABLE_NAME, VIEW_DEFINITION, CHECK_OPTION, IS_UPDATABLE, DEFINER, SECURITY_TYPE, CHARACTER_SET_CLIENT, COLLATION_CONNECTION FROM information_schema.VIEWS WHERE TABLE_SCHEMA = 'd'")[0];
        self::assertInstanceOf(ResultSet::class, $result1);
        self::assertSame([['v', 'select `d`.`p`.`id` AS `id`,`d`.`p`.`name` AS `name` from `d`.`p` where (`d`.`p`.`id` > 1)', 'CASCADED', 'YES', 'root@%', 'DEFINER', 'utf8mb4', 'utf8mb4_0900_ai_ci'], ['w', 'select count(0) AS `COUNT(*)` from `d`.`p`', 'NONE', 'NO', 'root@%', 'DEFINER', 'utf8mb4', 'utf8mb4_0900_ai_ci']], $result1->rows);
    }
}
