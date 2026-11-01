<?php

declare(strict_types=1);

namespace Tests\Unit\System\Privilege;

use MySqlMemory\Account\Identity;
use MySqlMemory\Account\Privileges;
use MySqlMemory\Evaluation\Compile\Connection;
use MySqlMemory\Evaluation\Context;
use MySqlMemory\Instance;
use MySqlMemory\System\Privilege\Grantees;
use MySqlMemory\System\Reading;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\GrammarRelease;

#[CoversClass(Grantees::class)]
#[Small]
final class GranteesTest extends TestCase
{
    public function testStoredOrdersTheAccountsByHostAndUser(): void
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
        $system = $s->instance->dictionary->system;
        self::assertNotNull($system);
        $reading = new Reading($s->instance, new Connection($s->variables, new Context($s->modes(), $s->diagnostics, $s->variables, 0.0), 'root', 'localhost', $s->id), $system->catalog->tables[0], GrammarRelease::MySql847);

        self::assertSame(['%:r', '%:root', '%:u1'], array_slice(array_map(static fn ($account): string => $account->identity->host . ':' . $account->identity->user, Grantees::stored($reading)), 0, 3));
    }

    public function testCheckedOrdersTheAccountsAsTheServerChecksThem(): void
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
        $system = $s->instance->dictionary->system;
        self::assertNotNull($system);
        $reading = new Reading($s->instance, new Connection($s->variables, new Context($s->modes(), $s->diagnostics, $s->variables, 0.0), 'root', 'localhost', $s->id), $system->catalog->tables[0], GrammarRelease::MySql847);

        self::assertSame(['localhost:mysql.infoschema', 'localhost:mysql.session', 'localhost:mysql.sys', 'localhost:root', 'localhost:u2', '%:root', '%:u1', '%:r'], array_map(static fn ($account): string => $account->identity->host . ':' . $account->identity->user, Grantees::checked($reading)));
    }

    public function testGranteeQuotesTheUserAndHost(): void
    {
        $account = (new Instance())->accounts->find(new Identity('root', '%'));
        self::assertNotNull($account);

        self::assertSame("'root'@'%'", Grantees::grantee($account));
    }

    public function testFlagsAnswersAColumnForEachPrivilege(): void
    {
        self::assertSame(['Select_priv' => 'Y', 'Insert_priv' => 'N', 'Grant_priv' => 'Y'], Grantees::flags(new Privileges(['SELECT' => true], true), ['SELECT', 'INSERT']));
    }

    public function testSetListsThePrivilegesInTheOrderOfTheSet(): void
    {
        self::assertSame(['Select,Delete,Grant', 'Update'], [Grantees::set(['DELETE' => true, 'SELECT' => true], Grantees::TABLE, true), Grantees::set(['UPDATE' => true], Grantees::COLUMN)]);
    }

    public function testTimeAnswersWhenTheServerStarted(): void
    {
        $instance = new Instance();
        $s = $instance->connect();
        $system = $instance->dictionary->system;
        self::assertNotNull($system);
        $reading = new Reading($s->instance, new Connection($s->variables, new Context($s->modes(), $s->diagnostics, $s->variables, 0.0), 'root', 'localhost', $s->id), $system->catalog->tables[0], GrammarRelease::MySql847);

        self::assertSame(date('Y-m-d H:i:s', (int) floor($instance->started)), Grantees::time($reading));
    }

    public function testGlobalAnswersTheStaticPrivileges(): void
    {
        self::assertSame(['SELECT', 'INSERT'], array_slice(Grantees::global(), 0, 2));
    }
}
