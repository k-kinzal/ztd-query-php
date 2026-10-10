<?php

declare(strict_types=1);

namespace Tests\Unit\System\Privilege;

use MySqlMemory\Account\Identity;
use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use MySqlMemory\System\Privilege\TablesPriv;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(TablesPriv::class)]
#[Small]
final class TablesPrivTest extends TestCase
{
    public function testRowsListsTheTableGrants(): void
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

        $result1 = $s->query('SELECT Host, Db, User, Table_name, Table_priv, Column_priv FROM mysql.tables_priv')[0];
        self::assertInstanceOf(ResultSet::class, $result1);
        self::assertSame([['localhost', 'd', 'u2', 't', 'Select,Delete', 'Select,Update'], ['localhost', 'mysql', 'mysql.session', 'user', 'Select', ''], ['localhost', 'sys', 'mysql.sys', 'sys_config', 'Select', '']], $result1->rows);
    }

    public function testTablesAnswersTheTablesAnAccountHoldsPrivilegesOn(): void
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
        $account = $s->instance->accounts->find(new Identity('u2', 'localhost'));
        self::assertNotNull($account);

        self::assertSame([['d', 't']], array_map(static fn (array $table): array => [$table[0], $table[1]], TablesPriv::tables($account)));
    }
}
