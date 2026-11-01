<?php

declare(strict_types=1);

namespace Tests\Unit\Command\Account\Role;

use MySqlMemory\Command\Account\Role\CreateRoleCommand;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(CreateRoleCommand::class)]
#[Small]
final class CreateRoleCommandTest extends TestCase
{
    public function testClearsDiagnosticsAnswersTrue(): void
    {
        self::assertTrue((new CreateRoleCommand())->clearsDiagnostics());
    }

    public function testPrepareReadsNamesWithoutChangingAccounts(): void
    {
        $session = (new Instance())->connect();
        $accounts = $session->instance->accounts->accounts;
        $role = new \SqlSemantics\Platform\MySql\Statement\Name\AccountName(new \SqlSemantics\Statement\Identifier\Name('full', \SqlSemantics\Statement\Identifier\NameQuote::Bare));
        $statement = new \SqlSemantics\Platform\MySql\Statement\Account\CreateRole(false, [$role]);
        (new CreateRoleCommand())->prepare($statement, $session);
        self::assertSame([4119], array_column($session->diagnostics->conditions, 1));
        self::assertSame($accounts, $session->instance->accounts->accounts);
        (new CreateRoleCommand())->prepare($statement, $session, false);
        self::assertSame([4119], array_column($session->diagnostics->conditions, 1));
    }

    public function testExecuteWarnsDuringLookupEvenWhenTheRoleAlreadyExists(): void
    {
        $session = (new Instance())->connect();
        $session->query("CREATE ROLE IF NOT EXISTS 'r'@'猫'");
        $created = $session->query('SHOW WARNINGS')[0];
        $session->query("CREATE ROLE IF NOT EXISTS 'r'@'猫'");
        $existing = $session->query('SHOW WARNINGS')[0];

        self::assertInstanceOf(ResultSet::class, $created);
        self::assertInstanceOf(ResultSet::class, $existing);
        self::assertCount(9, $created->rows);
        self::assertSame(['1366', '3163'], array_column($existing->rows, 1));
    }

    public function testExecuteCreatesLockedRoles(): void
    {
        $session = (new Instance())->connect();

        $session->query('CREATE ROLE r');

        $created = $session->query('SHOW CREATE USER r')[0];
        self::assertInstanceOf(ResultSet::class, $created);
        self::assertSame("CREATE USER `r`@`%` IDENTIFIED WITH 'caching_sha2_password' REQUIRE NONE PASSWORD EXPIRE ACCOUNT LOCK PASSWORD HISTORY DEFAULT PASSWORD REUSE INTERVAL DEFAULT PASSWORD REQUIRE CURRENT DEFAULT", $created->rows[0][0]);
    }

    public function testExecuteRefusesTheAnonymousUser(): void
    {
        $session = (new Instance())->connect();

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1396);
        $this->expectExceptionMessage('Operation CREATE ROLE failed for anonymous user');

        $session->query("CREATE ROLE ''");
    }

    public function testExecuteNotesARoleNamedTwiceIfNotExists(): void
    {
        $session = (new Instance())->connect();

        $session->query('CREATE ROLE IF NOT EXISTS r, r');

        $warnings = $session->query('SHOW WARNINGS')[0];
        self::assertInstanceOf(ResultSet::class, $warnings);
        self::assertSame([['Note', '3163', "Authorization ID 'r'@'%' already exists."]], $warnings->rows);
    }

}
