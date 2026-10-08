<?php

declare(strict_types=1);

namespace Tests\Unit\Command\Account;

use MySqlMemory\Account\Account;
use MySqlMemory\Account\Identity;
use MySqlMemory\Account\Privileges;
use MySqlMemory\Command\Account\RevokeCommand;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(RevokeCommand::class)]
#[Small]
final class RevokeCommandTest extends TestCase
{
    public function testClearsDiagnosticsAnswersTrue(): void
    {
        self::assertTrue((new RevokeCommand())->clearsDiagnostics());
    }

    public function testExecuteRevokesEveryLevelWithAllAtTheGlobalLevel(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE ROLE r');
        $session->query('CREATE USER u DEFAULT ROLE r');
        $session->query('GRANT SELECT ON d.* TO u WITH GRANT OPTION');

        $session->query('REVOKE ALL ON *.* FROM u');

        $grants = $session->query('SHOW GRANTS FOR u')[0];
        self::assertInstanceOf(ResultSet::class, $grants);
        self::assertSame([['GRANT USAGE ON *.* TO `u`@`%`'], ['GRANT `r`@`%` TO `u`@`%`']], $grants->rows);
    }

    public function testExecuteKeepsNothingWhenAnAccountIsMissing(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE USER u');
        $session->query('GRANT RELOAD ON *.* TO u');

        $session->run('REVOKE RELOAD ON *.* FROM u, nobody');

        $grants = $session->query('SHOW GRANTS FOR u')[0];
        self::assertInstanceOf(ResultSet::class, $grants);
        self::assertSame([['GRANT RELOAD ON *.* TO `u`@`%`']], $grants->rows);
    }

    public function testPrivilegesWarnsOfAnUnregisteredDynamicPrivilege(): void
    {
        $session = (new Instance())->connect();

        $session->query('REVOKE FOO_BAR ON *.* FROM root');

        $warnings = $session->query('SHOW WARNINGS')[0];
        self::assertInstanceOf(ResultSet::class, $warnings);
        self::assertSame([['Warning', '3929', "Dynamic privilege 'FOO_BAR' is not registered with the server."]], $warnings->rows);
    }

    public function testRevokeRefusesADatabaseWithoutGrants(): void
    {
        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1141);
        $this->expectExceptionMessage("There is no such grant defined for user 'u' on host '%'");

        (new RevokeCommand())->revoke(new Account(new Identity('u', '%')), ['DATABASE', 'd', ''], ['SELECT'], [], false, false, false, (new Instance())->connect());
    }

    public function testHoldsCountsAColumnPrivilege(): void
    {
        self::assertSame([true, false], [(new RevokeCommand())->holds(new Privileges([], false, ['a' => ['SELECT' => true]]), ['SELECT'], [], false, false), (new RevokeCommand())->holds(new Privileges(), [], ['UPDATE' => ['a']], false, false)]);
    }

    public function testMissingNamesTheLevel(): void
    {
        self::assertSame(
            ["There is no such grant defined for user 'u' on host '%' on table 't'", "There is no such grant defined for user 'u' on host '%' on routine 'p'"],
            [(new RevokeCommand())->missing(new Identity('u', '%'), ['TABLE', 'd', 't'])->getMessage(), (new RevokeCommand())->missing(new Identity('u', '%'), ['PROCEDURE', 'd', 'P'])->getMessage()],
        );
    }

    public function testFoundWarnsOfMissingAccountsItIgnores(): void
    {
        $session = (new Instance())->connect();

        $session->query('REVOKE SELECT ON *.* FROM nobody@localhost IGNORE UNKNOWN USER');

        $warnings = $session->query('SHOW WARNINGS')[0];
        self::assertInstanceOf(ResultSet::class, $warnings);
        self::assertSame([
            ['Warning', '1285', 'MySQL is started in --skip-name-resolve mode; you must restart it without this switch for this grant to work'],
            ['Warning', '3162', 'Authorization ID nobody does not exist.'],
        ], $warnings->rows);
    }

    public function testRolesWarnsOfAMissingRoleIfExists(): void
    {
        $session = (new Instance())->connect();

        $session->query('REVOKE IF EXISTS rx FROM CURRENT_USER(), nobody IGNORE UNKNOWN USER');

        $warnings = $session->query('SHOW WARNINGS')[0];
        self::assertInstanceOf(ResultSet::class, $warnings);
        self::assertSame([['Warning', '3523', 'Unknown authorization ID `rx`@`%`'], ['Warning', '3162', 'Authorization ID nobody does not exist.']], $warnings->rows);
    }

    public function testProxySucceedsWhenEveryAccountIsIgnored(): void
    {
        $session = (new Instance())->connect();

        $session->query('REVOKE PROXY ON root FROM nobody IGNORE UNKNOWN USER');

        $warnings = $session->query('SHOW WARNINGS')[0];
        self::assertInstanceOf(ResultSet::class, $warnings);
        self::assertSame([['Warning', '3162', 'Authorization ID nobody does not exist.']], $warnings->rows);
    }

    public function testEverythingRefusesAMissingAccount(): void
    {
        $session = (new Instance())->connect();

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1269);
        $this->expectExceptionMessage("Can't revoke all privileges for one or more of the requested users");

        $session->query('REVOKE ALL, GRANT OPTION FROM root, nobody');
    }
}
