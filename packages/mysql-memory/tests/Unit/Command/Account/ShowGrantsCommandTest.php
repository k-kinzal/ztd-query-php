<?php

declare(strict_types=1);

namespace Tests\Unit\Command\Account;

use MySqlMemory\Account\Accounts;
use MySqlMemory\Account\Identity;
use MySqlMemory\Command\Account\ShowGrantsCommand;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(ShowGrantsCommand::class)]
#[Small]
final class ShowGrantsCommandTest extends TestCase
{
    public function testClearsDiagnosticsAnswersTrue(): void
    {
        self::assertTrue((new ShowGrantsCommand())->clearsDiagnostics());
    }

    public function testExecuteWritesTheGrantsOfRootAtLocalhost(): void
    {
        $result = (new Instance())->connect()->query('SHOW GRANTS FOR root@localhost')[0];
        self::assertInstanceOf(ResultSet::class, $result);

        self::assertSame(['Grants for root@localhost', 4096, 3, 'GRANT PROXY ON ``@`` TO `root`@`localhost` WITH GRANT OPTION'], [$result->columns[0]->name, $result->columns[0]->length, count($result->rows), $result->rows[2][0]]);
    }

    public function testExecuteAddsTheRolesUsingNames(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE ROLE r1, r2');
        $session->query('CREATE USER u');
        $session->query('GRANT r1 TO u');
        $session->query('GRANT r2 TO r1');
        $session->query('GRANT EVENT ON *.* TO r2');

        $grants = $session->query('SHOW GRANTS FOR u USING r1')[0];
        self::assertInstanceOf(ResultSet::class, $grants);
        self::assertSame([['GRANT EVENT ON *.* TO `u`@`%`'], ['GRANT `r1`@`%` TO `u`@`%`']], $grants->rows);
    }

    public function testExecuteRefusesARoleThatIsNotGranted(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE USER u');

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(3530);
        $this->expectExceptionMessage('`r`@`%` is not granted to `u`@`%`');

        $session->query('SHOW GRANTS FOR u USING r');
    }

    public function testExecuteForgetsTheGrantsOfADroppedRoutine(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE PROCEDURE Pr() SELECT 1; CREATE USER u');
        $session->query('GRANT EXECUTE ON PROCEDURE pr TO u');
        $before = $session->query('SHOW GRANTS FOR u')[0];
        $session->query('DROP PROCEDURE PR');
        $after = $session->query('SHOW GRANTS FOR u')[0];

        self::assertInstanceOf(ResultSet::class, $before);
        self::assertInstanceOf(ResultSet::class, $after);
        self::assertSame([[['GRANT USAGE ON *.* TO `u`@`%`'], ['GRANT EXECUTE ON PROCEDURE `d`.`pr` TO `u`@`%`']], [['GRANT USAGE ON *.* TO `u`@`%`']]], [$before->rows, $after->rows]);
    }

    public function testExecuteRefusesAMissingAccount(): void
    {
        $session = (new Instance())->connect();

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1141);
        $this->expectExceptionMessage("There is no such grant defined for user 'u' on host 'h'");

        $session->query("SHOW GRANTS FOR u@'H'");
    }

    public function testThroughFollowsTheRolesOfRoles(): void
    {
        $accounts = Accounts::installed();
        $accounts->grant(new Identity('mysql.sys', 'localhost'), new Identity('mysql.session', 'localhost'), false);

        self::assertSame(['SHUTDOWN' => true, 'SUPER' => true], (new ShowGrantsCommand())->through([new Identity('mysql.session', 'localhost')], $accounts)->global->names);
    }
}
