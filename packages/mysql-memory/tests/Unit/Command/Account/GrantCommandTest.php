<?php

declare(strict_types=1);

namespace Tests\Unit\Command\Account;

use MySqlMemory\Account\Account;
use MySqlMemory\Account\Identity;
use MySqlMemory\Command\Account\GrantCommand;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(GrantCommand::class)]
#[Small]
final class GrantCommandTest extends TestCase
{
    public function testClearsDiagnosticsAnswersTrue(): void
    {
        self::assertTrue((new GrantCommand())->clearsDiagnostics());
    }

    public function testExecuteGrantsColumnAndTablePrivileges(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (a INT, b INT)');
        $session->query('CREATE USER u');

        $session->query('GRANT INSERT, REFERENCES (b), SELECT (a) ON t TO u');

        $grants = $session->query('SHOW GRANTS FOR u')[0];
        self::assertInstanceOf(ResultSet::class, $grants);
        self::assertSame([['GRANT USAGE ON *.* TO `u`@`%`'], ['GRANT SELECT (`a`), INSERT, REFERENCES (`b`) ON `d`.`t` TO `u`@`%`']], $grants->rows);
    }

    public function testExecuteWarnsOfAHostNameAndRefusesAMissingAccount(): void
    {
        $session = (new Instance())->connect();

        $session->run('GRANT SELECT ON *.* TO u@localhost');

        $warnings = $session->query('SHOW WARNINGS')[0];
        self::assertInstanceOf(ResultSet::class, $warnings);
        self::assertSame([
            ['Warning', '1285', 'MySQL is started in --skip-name-resolve mode; you must restart it without this switch for this grant to work'],
            ['Error', '1410', 'You are not allowed to create a user with GRANT'],
        ], $warnings->rows);
    }

    public function testExecuteRefusesAProxyGrant(): void
    {
        $session = (new Instance())->connect('root', '192.168.65.1');

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1698);
        $this->expectExceptionMessage("Access denied for user 'root'@'192.168.65.1'");

        $session->query('GRANT PROXY ON nobody TO root');
    }

    public function testPrivilegesRefusesAnUnregisteredDynamicPrivilege(): void
    {
        $session = (new Instance())->connect();

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1149);

        $session->query('GRANT FOO_BAR ON *.* TO root');
    }

    public function testPrivilegesRefusesAPrivilegeADatabaseDoesNotTake(): void
    {
        $session = (new Instance())->connect();

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1221);
        $this->expectExceptionMessage('Incorrect usage of DB GRANT and GLOBAL PRIVILEGES');

        $session->query('GRANT RELOAD ON d.* TO root');
    }

    public function testGrantGrantsDynamicPrivilegesWithGrantOption(): void
    {
        $account = new Account(new Identity('u', '%'));

        (new GrantCommand())->grant($account, ['GLOBAL', '', ''], [], [], ['BACKUP_ADMIN'], true, false, false);

        self::assertSame([['BACKUP_ADMIN' => true], false], [$account->grants->dynamic, $account->grants->global->grantOption]);
    }

    public function testAsRefusesAMissingAccount(): void
    {
        $session = (new Instance())->connect();

        $session->run('GRANT SELECT ON *.* TO root AS nobody');

        $warnings = $session->query('SHOW WARNINGS')[0];
        self::assertInstanceOf(ResultSet::class, $warnings);
        self::assertSame([
            ['Error', '1449', "The user specified as a definer ('nobody'@'%') does not exist"],
            ['Error', '3836', 'Either some of the authorization IDs in the AS clause are invalid or the current user lacks privileges to execute the statement.'],
        ], $warnings->rows);
    }

    public function testRolesRefusesALoop(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE ROLE r1');
        $session->query('CREATE USER v');
        $session->query('GRANT r1 TO v');

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(4027);
        $this->expectExceptionMessage('User account `r1`@`%` is directly or indirectly granted to the role `v`@`%`. The GRANT would create a loop');

        $session->query('GRANT v TO r1');
    }

    public function testRolesRefusesAMissingAccountFirst(): void
    {
        $session = (new Instance())->connect();

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(3523);
        $this->expectExceptionMessage('Unknown authorization ID `nobody`@`%`');

        $session->query('GRANT rx TO nobody');
    }

    public function testAccountsCreatesTheAccountsGrantNamesInMySql56(): void
    {
        $session = (new Instance('5.6.51'))->connect();

        $session->query("GRANT ALL ON *.* TO 'g'@'%' IDENTIFIED BY 'x' WITH GRANT OPTION");

        $grants = $session->query("SHOW GRANTS FOR 'g'@'%'")[0];
        self::assertInstanceOf(ResultSet::class, $grants);
        self::assertSame([["GRANT ALL PRIVILEGES ON *.* TO 'g'@'%' IDENTIFIED BY PASSWORD '*B69027D44F6E5EDC07F1AEAD1477967B16F28227' WITH GRANT OPTION"]], $grants->rows);
    }

    public function testAccountsRefusesAnAccountWithoutPasswordUnderNoAutoCreateUser(): void
    {
        $session = (new Instance('5.6.51'))->connect();
        $session->query("SET sql_mode = 'NO_AUTO_CREATE_USER'");

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1133);
        $this->expectExceptionMessage("Can't find any matching row in the user table");

        $session->query("GRANT SELECT ON *.* TO 'g'@'%' IDENTIFIED BY ''");
    }

    public function testAccountsWarnsOfCreatingAndChangingAccountsInMySql57(): void
    {
        $session = (new Instance('5.7.44'))->connect();
        $session->query('CREATE USER a');

        $session->query("GRANT SELECT ON *.* TO a, b IDENTIFIED BY PASSWORD '*7B9EBEED26AA52ED10C0F549FA863F13C39E0209' REQUIRE SSL");

        $warnings = $session->query('SHOW WARNINGS')[0];
        self::assertInstanceOf(ResultSet::class, $warnings);
        self::assertSame([
            ['Warning', '1287', "'IDENTIFIED BY PASSWORD' is deprecated and will be removed in a future release. Please use IDENTIFIED WITH <plugin> AS <hash> instead"],
            ['Warning', '1287', "Using GRANT statement to modify existing user's properties other than privileges is deprecated and will be removed in future release. Use ALTER USER statement for this operation."],
            ['Warning', '1287', 'Using GRANT for creating new user is deprecated and will be removed in future release. Create new user with CREATE USER statement.'],
        ], $warnings->rows);
    }
}
