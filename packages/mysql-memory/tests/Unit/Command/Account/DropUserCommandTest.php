<?php

declare(strict_types=1);

namespace Tests\Unit\Command\Account;

use MySqlMemory\Account\Identity;
use MySqlMemory\Command\Account\DropUserCommand;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(DropUserCommand::class)]
#[Small]
final class DropUserCommandTest extends TestCase
{
    public function testClearsDiagnosticsAnswersTrue(): void
    {
        self::assertTrue((new DropUserCommand())->clearsDiagnostics());
    }

    public function testExecuteRefusesMissingAccountsAndDropsNone(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE USER a');

        $error = $session->run('DROP USER zz, a, yy')[0];

        self::assertInstanceOf(SqlError::class, $error);
        self::assertSame([1396, "Operation DROP USER failed for 'zz'@'%','yy'@'%'", 'a'], [$error->getCode(), $error->getMessage(), $session->instance->accounts->find(Identity::of('a', null))?->identity->user]);
    }

    public function testExecuteRefusesAnAccountNamedTwice(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE ROLE r');

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1396);
        $this->expectExceptionMessage("Operation DROP ROLE failed for 'r'@'%'");

        $session->query('DROP ROLE r, r');
    }

    public function testExecuteNotesMissingAccountsIfExists(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE USER a');

        $session->query("DROP USER IF EXISTS 'a''b', a");

        $warnings = $session->query('SHOW WARNINGS')[0];
        self::assertInstanceOf(ResultSet::class, $warnings);
        self::assertSame([[['Note', '3162', "Authorization ID 'a\\'b'@'%' does not exist."]], null], [$warnings->rows, $session->instance->accounts->find(Identity::of('a', null))]);
    }

    public function testExecuteCommitsTheOpenTransactionEvenWhenItFails(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (a INT); BEGIN; INSERT INTO t VALUES (2)');
        $session->run('DROP USER nobody');
        $session->query('ROLLBACK');

        $rows = $session->query('SELECT a FROM t')[0];

        self::assertInstanceOf(ResultSet::class, $rows);
        self::assertSame([['2']], $rows->rows);
    }

    public function testExecuteRevokesADroppedRole(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE ROLE r');
        $session->query('CREATE USER u DEFAULT ROLE r');

        $session->query('DROP ROLE r');

        $grants = $session->query('SHOW GRANTS FOR u')[0];
        self::assertInstanceOf(ResultSet::class, $grants);
        self::assertSame([['GRANT USAGE ON *.* TO `u`@`%`']], $grants->rows);
    }
}
