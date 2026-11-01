<?php

declare(strict_types=1);

namespace Tests\Unit\System\Privilege;

use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use MySqlMemory\System\Privilege\UserPrivileges;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(UserPrivileges::class)]
#[Small]
final class UserPrivilegesTest extends TestCase
{
    public function testRowsListsTheGlobalPrivilegesOfEachAccount(): void
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

        $result1 = $s->query("SELECT * FROM information_schema.USER_PRIVILEGES WHERE GRANTEE NOT LIKE '%root%' AND GRANTEE NOT LIKE '%mysql.%'")[0];
        self::assertInstanceOf(ResultSet::class, $result1);
        self::assertSame([["'u2'@'localhost'", 'def', 'USAGE', 'NO'], ["'u1'@'%'", 'def', 'SELECT', 'YES'], ["'u1'@'%'", 'def', 'INSERT', 'YES'], ["'u1'@'%'", 'def', 'BACKUP_ADMIN', 'NO'], ["'r'@'%'", 'def', 'USAGE', 'NO']], $result1->rows);
        $result2 = $s->query("SELECT GRANTEE, PRIVILEGE_TYPE FROM information_schema.USER_PRIVILEGES WHERE GRANTEE LIKE '%mysql.sys%'")[0];
        self::assertInstanceOf(ResultSet::class, $result2);
        self::assertSame([["'mysql.sys'@'localhost'", 'USAGE'], ["'mysql.sys'@'localhost'", 'SYSTEM_USER'], ["'mysql.sys'@'localhost'", 'FIREWALL_EXEMPT'], ["'mysql.sys'@'localhost'", 'AUDIT_ABORT_EXEMPT']], $result2->rows);
    }
}
