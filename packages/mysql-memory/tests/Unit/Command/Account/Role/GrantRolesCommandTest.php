<?php

declare(strict_types=1);

namespace Tests\Unit\Command\Account\Role;

use MySqlMemory\Account\Identity;
use MySqlMemory\Command\Account\Role\GrantRolesCommand;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Instance;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(GrantRolesCommand::class)]
#[Small]
final class GrantRolesCommandTest extends TestCase
{
    public function testClearsDiagnosticsAnswersTrue(): void
    {
        self::assertTrue((new GrantRolesCommand())->clearsDiagnostics());
    }

    public function testPrepareReadsNamesWithoutChangingAccounts(): void
    {
        $session = (new Instance())->connect();
        $accounts = $session->instance->accounts->accounts;
        $role = new \SqlSemantics\Platform\MySql\Statement\Name\AccountName(new \SqlSemantics\Statement\Identifier\Name('full', \SqlSemantics\Statement\Identifier\NameQuote::Bare));
        $statement = new \SqlSemantics\Platform\MySql\Statement\Account\Privilege\GrantRoles([new \SqlSemantics\Platform\MySql\Statement\Account\Privilege\Item\GrantedRole($role)], [new \SqlSemantics\Platform\MySql\Statement\Name\CurrentUser()]);
        (new GrantRolesCommand())->prepare($statement, $session);
        self::assertSame([4119], array_column($session->diagnostics->conditions, 1));
        self::assertSame($accounts, $session->instance->accounts->accounts);
        (new GrantRolesCommand())->prepare($statement, $session, false);
        self::assertSame([4119], array_column($session->diagnostics->conditions, 1));
    }

    public function testExecuteRefusesALoop(): void
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

    public function testExecuteLooksUpRolesForEachRecipientBeforeAdvancing(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE USER u');
        $this->expectException(SqlError::class);
        $this->expectExceptionMessage('Unknown authorization ID `missing_role`@`%`');

        $session->query('GRANT missing_role TO u, missing_user');
    }

    public function testExecuteRestoresEarlierGrantsAfterALaterRecipientFailure(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE USER u');
        $session->query('CREATE ROLE r');

        $session->run('GRANT r TO u, missing_user');

        self::assertSame([], $session->instance->accounts->roles(new Identity('u', '%')));
        self::assertSame([['Error', 3523, 'Unknown authorization ID `missing_user`@`%`']], $session->diagnostics->conditions);
    }

    public function testExecuteRestoresEarlierRolesAfterALaterRoleFailure(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE USER u');
        $session->query('CREATE ROLE r');

        $session->run('GRANT r, missing_role TO u');

        self::assertSame([], $session->instance->accounts->roles(new Identity('u', '%')));
        self::assertSame([['Error', 3523, 'Unknown authorization ID `missing_role`@`%`']], $session->diagnostics->conditions);
    }

    public function testExecuteRefusesAMissingAccountFirst(): void
    {
        $session = (new Instance())->connect();

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(3523);
        $this->expectExceptionMessage('Unknown authorization ID `nobody`@`%`');

        $session->query('GRANT rx TO nobody');
    }

}
