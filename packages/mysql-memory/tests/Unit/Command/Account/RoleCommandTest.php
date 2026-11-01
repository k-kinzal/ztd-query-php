<?php

declare(strict_types=1);

namespace Tests\Unit\Command\Account;

use MySqlMemory\Command\Account\RoleCommand;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(RoleCommand::class)]
#[Small]
final class RoleCommandTest extends TestCase
{
    public function testClearsDiagnosticsAnswersTrue(): void
    {
        self::assertTrue((new RoleCommand())->clearsDiagnostics());
    }

    public function testExecuteActivatesTheRolesOfTheSession(): void
    {
        $instance = new Instance();
        $root = $instance->connect();
        $root->query('CREATE ROLE r1, r3');
        $root->query('CREATE USER u');
        $root->query('GRANT r1, r3 TO u');
        $root->query('SET DEFAULT ROLE r3 TO u');
        $session = $instance->connect('u');

        $roles = $session->query('SELECT CURRENT_ROLE()')[0];
        self::assertInstanceOf(ResultSet::class, $roles);
        $before = $roles->rows;
        $session->query('SET ROLE ALL');
        $roles = $session->query('SELECT CURRENT_ROLE()')[0];
        self::assertInstanceOf(ResultSet::class, $roles);
        $all = $roles->rows;
        $session->query('SET ROLE ALL EXCEPT r3');

        $roles = $session->query('SELECT CURRENT_ROLE()')[0];
        self::assertInstanceOf(ResultSet::class, $roles);
        self::assertSame([[['`r3`@`%`']], [['`r1`@`%`,`r3`@`%`']], [['`r1`@`%`']]], [$before, $all, $roles->rows]);
    }

    public function testExecuteRefusesARoleThatIsNotGranted(): void
    {
        $session = (new Instance())->connect();

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(3530);
        $this->expectExceptionMessage('`r`@`%` is not granted to `root`@`%`');

        $session->query('SET ROLE r');
    }

    public function testExecuteSetsNoDefaultRoleForAMissingAccount(): void
    {
        $session = (new Instance())->connect();

        $session->query('SET DEFAULT ROLE NONE TO nobody');

        $warnings = $session->query('SHOW WARNINGS')[0];
        self::assertInstanceOf(ResultSet::class, $warnings);
        self::assertSame([], $warnings->rows);
    }

    public function testExecuteRefusesAllForAMissingAccount(): void
    {
        $session = (new Instance())->connect();

        $session->run('SET DEFAULT ROLE ALL TO nobody');

        $warnings = $session->query('SHOW WARNINGS')[0];
        self::assertInstanceOf(ResultSet::class, $warnings);
        self::assertSame([['Error', '3523', 'Unknown authorization ID `nobody`@`%`'], ['Error', '3526', 'Failed to set default roles']], $warnings->rows);
    }

    public function testSelectedAnswersTheDefaultRoles(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE ROLE r');
        $session->query('CREATE USER u DEFAULT ROLE r');

        $session->query('ALTER USER u DEFAULT ROLE NONE');

        $created = $session->query('SHOW CREATE USER u')[0];
        self::assertInstanceOf(ResultSet::class, $created);
        self::assertSame("CREATE USER `u`@`%` IDENTIFIED WITH 'caching_sha2_password' REQUIRE NONE PASSWORD EXPIRE DEFAULT ACCOUNT UNLOCK PASSWORD HISTORY DEFAULT PASSWORD REUSE INTERVAL DEFAULT PASSWORD REQUIRE CURRENT DEFAULT", $created->rows[0][0]);
    }
}
