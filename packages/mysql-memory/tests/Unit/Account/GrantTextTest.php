<?php

declare(strict_types=1);

namespace Tests\Unit\Account;

use MySqlMemory\Account\Catalog;
use MySqlMemory\Account\Grants;
use MySqlMemory\Account\GrantText;
use MySqlMemory\Account\Identity;
use MySqlMemory\Account\Privileges;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(GrantText::class)]
#[Small]
final class GrantTextTest extends TestCase
{
    public function testLinesWritesUsageForAnAccountWithoutPrivileges(): void
    {
        self::assertSame(['GRANT USAGE ON *.* TO `u`@`%`'], (new GrantText())->lines(new Identity('u', '%'), new Grants(), []));
    }

    public function testLinesWritesEachLevelInTheOrderOfTheServer(): void
    {
        $grants = new Grants(new Privileges(['RELOAD' => true, 'SELECT' => true], true), ['ROLE_ADMIN' => false, 'BACKUP_ADMIN' => false, 'XA_RECOVER_ADMIN' => true]);
        $grants->database('zdb')->add(['SELECT']);
        $grants->database('m_b')->add(['SELECT']);
        $grants->database('Mdb')->add(Catalog::DATABASE);
        $grants->table('d', 't')->add(['INSERT']);
        $grants->table('d', 't')->addColumns('SELECT', ['b', 'a']);
        $grants->routine('PROCEDURE', 'd', 'p')->add(['ALTER ROUTINE', 'EXECUTE']);
        $roles = ["r2\0%" => [new Identity('r2', '%'), false], "r1\0%" => [new Identity('r1', '%'), true]];

        self::assertSame([
            'GRANT SELECT, RELOAD ON *.* TO `u`@`%` WITH GRANT OPTION',
            'GRANT BACKUP_ADMIN,ROLE_ADMIN ON *.* TO `u`@`%`',
            'GRANT XA_RECOVER_ADMIN ON *.* TO `u`@`%` WITH GRANT OPTION',
            'GRANT ALL PRIVILEGES ON `Mdb`.* TO `u`@`%`',
            'GRANT SELECT ON `zdb`.* TO `u`@`%`',
            'GRANT SELECT ON `m_b`.* TO `u`@`%`',
            'GRANT SELECT (`a`, `b`), INSERT ON `d`.`t` TO `u`@`%`',
            'GRANT EXECUTE, ALTER ROUTINE ON PROCEDURE `d`.`p` TO `u`@`%`',
            'GRANT `r2`@`%` TO `u`@`%`',
            'GRANT `r1`@`%` TO `u`@`%` WITH ADMIN OPTION',
        ], (new GrantText())->lines(new Identity('u', '%'), $grants, $roles));
    }

    public function testGlobalLinesWritesTheStaticThenTheDynamicPrivileges(): void
    {
        $grants = new Grants(new Privileges(['SELECT' => true]), ['XA_RECOVER_ADMIN' => true, 'BACKUP_ADMIN' => false]);

        self::assertSame([
            'GRANT SELECT ON *.* TO `u`@`%`',
            'GRANT BACKUP_ADMIN ON *.* TO `u`@`%`',
            'GRANT XA_RECOVER_ADMIN ON *.* TO `u`@`%` WITH GRANT OPTION',
        ], (new GrantText())->globalLines($grants, ' TO `u`@`%`', \SqlSemantics\Contract\GrammarRelease::MySql847, null));
    }

    public function testObjectLinesWritesTheDatabasesTablesAndRoutines(): void
    {
        $grants = new Grants();
        $grants->routine('FUNCTION', 'd', 'f')->add(['EXECUTE']);
        $grants->table('d', 't')->add(Catalog::TABLE);
        $grants->database('d')->add(['SELECT']);

        self::assertSame([
            'GRANT SELECT ON `d`.* TO `u`@`%`',
            'GRANT ALL PRIVILEGES ON `d`.`t` TO `u`@`%`',
            'GRANT EXECUTE ON FUNCTION `d`.`f` TO `u`@`%`',
        ], (new GrantText())->objectLines($grants, ' TO `u`@`%`', false));
    }

    public function testRoleLinesWritesTheProxiesThenTheRoles(): void
    {
        $grants = new Grants(proxies: ["p\0%" => [new Identity('p', '%'), true]]);

        self::assertSame([
            "GRANT PROXY ON 'p'@'%' TO 'u'@'%' WITH GRANT OPTION",
            "GRANT `r`@`%` TO 'u'@'%'",
        ], (new GrantText())->roleLines($grants, ["r\0%" => [new Identity('r', '%'), false]], " TO 'u'@'%'", true));
    }

    public function testPrivilegesWritesUsageWithOnlyGrantOption(): void
    {
        self::assertSame('USAGE', (new GrantText())->privileges(new Privileges([], true), Catalog::TABLE));
    }

    public function testQuoteDoublesBackticks(): void
    {
        self::assertSame('`ac``ct`', (new GrantText())->quote('ac`ct'));
    }

    public function testLinesWritesTheFormOfMySql57(): void
    {
        $grants = new Grants();
        $grants->global->add((new Catalog(\SqlSemantics\Contract\GrammarRelease::MySql5744))->statics());
        $grants->database('zz')->add(['SELECT']);
        $grants->database('a%')->add(['SELECT']);
        $grants->database('aa')->add(['SELECT']);
        $grants->table('d', 't')->addColumns('SELECT', ['a', 'b']);

        self::assertSame([
            "GRANT ALL PRIVILEGES ON *.* TO 'u'@'%'",
            "GRANT SELECT ON `zz`.* TO 'u'@'%'",
            "GRANT SELECT ON `aa`.* TO 'u'@'%'",
            "GRANT SELECT ON `a%`.* TO 'u'@'%'",
            "GRANT SELECT (a, b) ON `d`.`t` TO 'u'@'%'",
        ], (new GrantText())->lines(new Identity('u', '%'), $grants, [], \SqlSemantics\Contract\GrammarRelease::MySql5744));
    }

    public function testAccountWritesThePasswordTlsAndLimitsOfMySql56(): void
    {
        $account = new \MySqlMemory\Account\Account(new Identity('u', '%'), 'mysql_native_password', '*7B9EBEED26AA52ED10C0F549FA863F13C39E0209', tls: \SqlSemantics\Platform\MySql\Statement\Account\Option\TlsKind::Specified, tlsConditions: ['SUBJECT' => 's', 'CIPHER' => 'c'], limits: ['MAX_QUERIES_PER_HOUR' => 5, 'MAX_UPDATES_PER_HOUR' => 0, 'MAX_CONNECTIONS_PER_HOUR' => 0, 'MAX_USER_CONNECTIONS' => 8]);

        self::assertSame(" IDENTIFIED BY PASSWORD '*7B9EBEED26AA52ED10C0F549FA863F13C39E0209' REQUIRE SUBJECT 's' CIPHER 'c' WITH MAX_QUERIES_PER_HOUR 5 MAX_USER_CONNECTIONS 8", (new GrantText())->account($account, ''));
        self::assertSame(" IDENTIFIED BY PASSWORD '*7B9EBEED26AA52ED10C0F549FA863F13C39E0209' REQUIRE SUBJECT 's' CIPHER 'c' WITH GRANT OPTION MAX_QUERIES_PER_HOUR 5 MAX_USER_CONNECTIONS 8", (new GrantText())->account($account, ' WITH GRANT OPTION'));
    }
}
