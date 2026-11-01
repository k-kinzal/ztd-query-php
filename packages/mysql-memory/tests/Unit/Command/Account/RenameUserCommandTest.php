<?php

declare(strict_types=1);

namespace Tests\Unit\Command\Account;

use MySqlMemory\Account\Account;
use MySqlMemory\Account\Identity;
use MySqlMemory\Command\Account\RenameUserCommand;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Evaluation\Context;
use MySqlMemory\Instance;
use MySqlMemory\Session\Execution;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Account\RenameUser;
use SqlSemantics\Platform\MySql\Statement\Account\User\UserRenaming;
use SqlSemantics\Platform\MySql\Statement\Name\AccountName;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(RenameUserCommand::class)]
#[Small]
final class RenameUserCommandTest extends TestCase
{
    public function testClearsDiagnosticsAnswersTrue(): void
    {
        self::assertTrue((new RenameUserCommand())->clearsDiagnostics());
    }

    public function testPrepareReadsNameWarningsWithoutChangingAccounts(): void
    {
        $session = (new Instance())->connect();
        $accounts = $session->instance->accounts->accounts;
        $statement = new RenameUser([new UserRenaming(new AccountName(new Name('full', \SqlSemantics\Statement\Identifier\NameQuote::Bare)), new \SqlSemantics\Platform\MySql\Statement\Name\CurrentUser())]);
        (new RenameUserCommand())->prepare($statement, $session);
        self::assertSame([4119], array_column($session->diagnostics->conditions, 1));
        self::assertSame($accounts, $session->instance->accounts->accounts);
    }

    public function testApplyRenamesInOrderKeepingThePrivileges(): void
    {
        $session = (new Instance())->connect();
        $account = new Account(new Identity('a', '%'));
        $account->grants->database('d')->add(['SELECT']);
        $session->instance->accounts->add($account);
        $statement = new RenameUser(array_map(static fn (array $pair): UserRenaming => new UserRenaming(new AccountName(new Name($pair[0])), new AccountName(new Name($pair[1]))), [['a', 'b'], ['b', 'c']]));
        (new RenameUserCommand())->apply($statement, $session, new Context($session->modes(), $session->diagnostics, $session->variables, $session->variables->instant()));
        self::assertSame($account, $session->instance->accounts->find(new Identity('c', '%')));
        self::assertSame(['SELECT' => true], $account->grants->database('d')->names);
        self::assertNull($session->instance->accounts->find(new Identity('a', '%')));
        self::assertNull($session->instance->accounts->find(new Identity('b', '%')));
    }

    public function testExecuteRefusesEveryFailingPair(): void
    {
        $session = (new Instance())->connect();
        $session->instance->accounts->add(new Account(new Identity('a', '%')));
        $session->instance->accounts->add(new Account(new Identity('w', '%')));
        $session->instance->accounts->add(new Account(new Identity('v', '%')));
        $statement = new RenameUser(array_map(static fn (array $pair): UserRenaming => new UserRenaming(new AccountName(new Name($pair[0])), new AccountName(new Name($pair[1]))), [['a', 'b'], ['nobody', 'x'], ['w', 'v']]));
        $statement = (new \MySqlMemory\Session\Statement\Binding())->statement($session, $statement);

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1396);
        $this->expectExceptionMessage("Operation RENAME USER failed for 'nobody'@'%','w'@'%'");

        (new Execution($session))->statement($statement);
    }
    public function testRenameKeepsCacheAndGrantRowIdentitiesApart(): void
    {
        $session = (new Instance())->connect();
        $session->instance->accounts->add(new Account(new Identity('a', '猫')));
        $command = new RenameUserCommand();
        self::assertTrue($command->rename(new Identity('a', 'é'), new Identity('b', 'ö'), $session));
        $account = $session->instance->accounts->find(new Identity('a', '猫'));
        self::assertNotNull($account);
        self::assertSame('b', $account->storageIdentity()->user);
        self::assertNull($session->instance->accounts->find(new Identity('b', 'ö')));
    }

    public function testConversionsFollowGrantReadsBeforeEachWrite(): void
    {
        $session = (new Instance())->connect();
        $account = new Account(new Identity('a', '猫'));
        $account->grants->database('d')->add(['SELECT']);
        $account->grants->dynamic['BACKUP_ADMIN'] = false;
        (new RenameUserCommand())->conversions($account->identity, new Identity('b', '犬'), $account, $session);
        $messages = array_column($session->diagnostics->conditions, 2);

        self::assertCount(11, $messages);
        self::assertStringContainsString('\\xE7\\x8C\\xAB', $messages[0]);
        self::assertStringContainsString('\\xE7\\x8A\\xAC', $messages[1]);
        self::assertStringContainsString("column 'HOST'", $messages[9]);
        self::assertStringContainsString("column 'HOST'", $messages[10]);
    }

}
