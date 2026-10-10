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
}
