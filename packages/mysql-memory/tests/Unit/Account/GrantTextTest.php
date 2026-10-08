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

    public function testPrivilegesWritesUsageWithOnlyGrantOption(): void
    {
        self::assertSame('USAGE', (new GrantText())->privileges(new Privileges([], true), Catalog::TABLE));
    }

    public function testQuoteDoublesBackticks(): void
    {
        self::assertSame('`ac``ct`', (new GrantText())->quote('ac`ct'));
    }
}
