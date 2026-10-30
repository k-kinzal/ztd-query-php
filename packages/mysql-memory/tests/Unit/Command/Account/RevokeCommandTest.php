<?php

declare(strict_types=1);

namespace Tests\Unit\Command\Account;

use MySqlMemory\Account\Account;
use MySqlMemory\Account\Identity;
use MySqlMemory\Account\Privileges;
use MySqlMemory\Command\Account\RevokeCommand;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Instance;
use MySqlMemory\Result\Completion;
use MySqlMemory\Result\ResultSet;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(RevokeCommand::class)]
#[Small]
final class RevokeCommandTest extends TestCase
{
    public function testPrepareRequiresTheCurrentDatabaseBeforeAConditionalTarget(): void
    {
        $session = (new Instance())->connect();
        $request = new \SqlSemantics\Platform\MySql\Statement\Account\Privilege\RevokePrivileges(false, [new \SqlSemantics\Platform\MySql\Statement\Account\Privilege\Item\AllPrivileges()], \SqlSemantics\Platform\MySql\Statement\Account\Privilege\Level\ObjectKind::Procedure, new \SqlSemantics\Platform\MySql\Statement\Account\Privilege\Level\CurrentDatabaseLevel(), [new \SqlSemantics\Platform\MySql\Statement\Account\User\UserSpecification(new \SqlSemantics\Platform\MySql\Statement\Name\CurrentUser())]);
        $this->expectExceptionCode(1046);
        (new RevokeCommand())->prepare($request, $session);
    }

    public function testRolesPreservesEarlierWarningsWhenALaterRecipientFails(): void
    {
        $session = (new Instance())->connect();
        $reply = $session->run('REVOKE IF EXISTS rx FROM CURRENT_USER(), nobody')[0];
        self::assertInstanceOf(SqlError::class, $reply);
        self::assertSame(3523, $reply->getCode());
        self::assertSame([['Warning', 3523, 'Unknown authorization ID `rx`@`%`'], ['Error', 3523, 'Unknown authorization ID `nobody`@`%`']], $session->diagnostics->conditions);
    }

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

    /**
     * @return iterable<string, array{string, string, list<int>}>
     */
    public static function providerUsage(): iterable
    {
        yield '5.6 database' => ['5.6.51', 'd.*', [1141]];
        yield '5.7 database' => ['5.7.44', 'd.*', [1141]];
        yield '8.0 database' => ['8.0.44', 'd.*', [1141]];
        yield '8.4 database' => ['8.4.7', 'd.*', []];
        yield '9.1 database' => ['9.1.0', 'd.*', []];
        yield '8.4 table' => ['8.4.7', 'd.t', [1147]];
        yield '8.4 routine' => ['8.4.7', 'PROCEDURE d.p', [1403]];
    }

    /**
     * @param list<int> $errors The missing-grant errors for an empty privilege list
     */
    #[DataProvider('providerUsage')]
    public function testRevokeChecksUsageAtTheRequestedLevel(string $release, string $level, array $errors): void
    {
        $session = (new Instance($release))->connect();
        $session->query('CREATE USER u');

        $session->run('REVOKE USAGE ON ' . $level . ' FROM u');

        self::assertSame($errors, array_column($session->diagnostics->conditions, 1));
    }

    public function testHoldsCountsAColumnPrivilege(): void
    {
        self::assertSame([true, false], [(new RevokeCommand())->holds(new Privileges([], false, ['a' => ['SELECT' => true]]), ['SELECT'], [], false, false), (new RevokeCommand())->holds(new Privileges(), [], ['UPDATE' => ['a']], false, false)]);
    }

    public function testRequiresHeldDistinguishesRoutineGrantChecks(): void
    {
        self::assertTrue((new RevokeCommand())->requiresHeld('TABLE', \SqlSemantics\Contract\GrammarRelease::MySql8044));
        self::assertFalse((new RevokeCommand())->requiresHeld('PROCEDURE', \SqlSemantics\Contract\GrammarRelease::MySql8044));
        self::assertTrue((new RevokeCommand())->requiresHeld('PROCEDURE', \SqlSemantics\Contract\GrammarRelease::MySql847));
    }

    public function testMissingNamesTheLevel(): void
    {
        self::assertSame(
            ["There is no such grant defined for user 'u' on host '%' on table 't'", "There is no such grant defined for user 'u' on host '%' on routine 'P'"],
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

    public function testRolesWarnsOfAnUnknownRoleOnceForEachAccountNamed(): void
    {
        $session = (new Instance())->connect();
        $session->query('REVOKE IF EXISTS r FROM CURRENT_USER, nobody, CURRENT_USER IGNORE UNKNOWN USER');

        self::assertSame([['Warning', 3523, 'Unknown authorization ID `r`@`%`'], ['Warning', 3162, 'Authorization ID nobody does not exist.'], ['Warning', 3523, 'Unknown authorization ID `r`@`%`']], $session->diagnostics->conditions);
    }
    public function testEverythingAtGlobalScopeRetainsOnlyMissingRecipientWarnings(): void
    {
        $session = (new Instance('8.4.7'))->connect();
        $session->query("REVOKE ALL ON *.* FROM a@'named.host',b@'other.host' IGNORE UNKNOWN USER");
        self::assertSame([['Warning', 3162, 'Authorization ID a does not exist.'], ['Warning', 3162, 'Authorization ID b does not exist.']], $session->diagnostics->conditions);
    }

    public function testEverythingAtGlobalScopeRestoresEarlierGrantsWhenTheLastRecipientFails(): void
    {
        $session = (new Instance('8.4.7'))->connect();
        $session->query("CREATE USER a@'named.host'; GRANT SELECT ON d.* TO a@'named.host'");
        $reply = $session->run("REVOKE ALL ON *.* FROM a@'named.host',missing");
        self::assertInstanceOf(SqlError::class, $reply[0]);
        self::assertSame(1269, $reply[0]->getCode());
        $grants = $session->query("SHOW GRANTS FOR a@'named.host'")[0];
        self::assertInstanceOf(ResultSet::class, $grants);
        self::assertSame([['GRANT USAGE ON *.* TO `a`@`named.host`'], ['GRANT SELECT ON `d`.* TO `a`@`named.host`']], $grants->rows);
    }


    public function testExecuteDoesNotCountRetainedLegacyConditionsAsNewWarnings(): void
    {
        $session = (new Instance('5.6.51', databases: ['d']))->connect(database: 'd');
        $session->query('CREATE USER u; GRANT SELECT ON *.* TO u');
        self::assertInstanceOf(SqlError::class, $session->run('SELECT * FROM absent')[0]);
        $before = $session->diagnostics->conditions;
        $reply = $session->query('REVOKE SELECT ON *.* FROM u')[0];

        self::assertInstanceOf(Completion::class, $reply);
        self::assertSame(0, $reply->warnings);
        self::assertSame($before, $session->diagnostics->conditions);
    }
}
