<?php

declare(strict_types=1);

namespace Tests\Unit\Command\Account;

use MySqlMemory\Command\Account\RenameUserCommand;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(RenameUserCommand::class)]
#[Small]
final class RenameUserCommandTest extends TestCase
{
    public function testClearsDiagnosticsAnswersTrue(): void
    {
        self::assertTrue((new RenameUserCommand())->clearsDiagnostics());
    }

    public function testExecuteRenamesInOrderKeepingThePrivileges(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE USER a');
        $session->query('GRANT SELECT ON d.* TO a');

        $session->query('RENAME USER a TO b, b TO c');

        $grants = $session->query('SHOW GRANTS FOR c')[0];
        self::assertInstanceOf(ResultSet::class, $grants);
        self::assertSame([['GRANT USAGE ON *.* TO `c`@`%`'], ['GRANT SELECT ON `d`.* TO `c`@`%`']], $grants->rows);
    }

    public function testExecuteRefusesEveryFailingPair(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE USER a, w, v');

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1396);
        $this->expectExceptionMessage("Operation RENAME USER failed for 'nobody'@'%','w'@'%'");

        $session->query('RENAME USER a TO b, nobody TO x, w TO v');
    }

    public function testExecuteRefusesToRenameToARole(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE ROLE r');
        $session->query('CREATE USER u DEFAULT ROLE r');

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(3532);
        $this->expectExceptionMessage('Renaming of a role identifier is forbidden');

        $session->query('RENAME USER nobody TO r');
    }
    public function testRenameKeepsCacheAndGrantRowIdentitiesApart(): void
    {
        $session = (new Instance())->connect();
        $session->query("CREATE USER 'a'@'猫'");
        $command = new RenameUserCommand();
        self::assertTrue($command->rename(new \MySqlMemory\Account\Identity('a', 'é'), new \MySqlMemory\Account\Identity('b', 'ö'), $session));
        $account = $session->instance->accounts->find(new \MySqlMemory\Account\Identity('a', '猫'));
        self::assertNotNull($account);
        self::assertSame('b', $account->storageIdentity()->user);
        self::assertNull($session->instance->accounts->find(new \MySqlMemory\Account\Identity('b', 'ö')));
    }

    public function testConversionsFollowGrantReadsBeforeEachWrite(): void
    {
        $session = (new Instance())->connect();
        $account = new \MySqlMemory\Account\Account(new \MySqlMemory\Account\Identity('a', '猫'));
        $account->grants->database('d')->add(['SELECT']);
        $account->grants->dynamic['BACKUP_ADMIN'] = false;
        (new RenameUserCommand())->conversions($account->identity, new \MySqlMemory\Account\Identity('b', '犬'), $account, $session);
        $messages = array_column($session->diagnostics->conditions, 2);

        self::assertCount(11, $messages);
        self::assertStringContainsString('\\xE7\\x8C\\xAB', $messages[0]);
        self::assertStringContainsString('\\xE7\\x8A\\xAC', $messages[1]);
        self::assertStringContainsString("column 'HOST'", $messages[9]);
        self::assertStringContainsString("column 'HOST'", $messages[10]);
    }

}
