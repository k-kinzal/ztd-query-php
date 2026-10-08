<?php

declare(strict_types=1);

namespace Tests\Unit\System\Privilege;

use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use MySqlMemory\System\Privilege\GlobalGrants;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(GlobalGrants::class)]
#[Small]
final class GlobalGrantsTest extends TestCase
{
    public function testRowsListsTheDynamicPrivileges(): void
    {
        $s = (new Instance())->connect();
        $s->query('CREATE DATABASE d');
        $s->query('USE d');
        $s->query('CREATE TABLE t (a INT, b INT)');
        $s->query("CREATE USER 'u1'@'%' IDENTIFIED BY 'x'");
        $s->query("CREATE USER 'u2'@'localhost'");
        $s->query("CREATE ROLE 'r'");
        $s->query("GRANT SELECT, INSERT ON *.* TO 'u1'@'%' WITH GRANT OPTION");
        $s->query("GRANT SELECT, UPDATE, CREATE VIEW ON d.* TO 'u1'@'%'");
        $s->query("GRANT SELECT, DELETE ON d.t TO 'u2'@'localhost'");
        $s->query("GRANT SELECT (a), UPDATE (a, b) ON d.t TO 'u2'@'localhost'");
        $s->query("GRANT 'r' TO 'u2'@'localhost' WITH ADMIN OPTION");
        $s->query("SET DEFAULT ROLE 'r' TO 'u2'@'localhost'");
        $s->query("GRANT BACKUP_ADMIN ON *.* TO 'u1'@'%'");

        $result1 = $s->query("SELECT * FROM mysql.global_grants WHERE USER = 'u1'")[0];
        self::assertInstanceOf(ResultSet::class, $result1);
        self::assertSame([['u1', '%', 'BACKUP_ADMIN', 'N']], $result1->rows);
    }
}
