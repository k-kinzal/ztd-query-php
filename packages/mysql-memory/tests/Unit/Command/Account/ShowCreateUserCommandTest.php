<?php

declare(strict_types=1);

namespace Tests\Unit\Command\Account;

use MySqlMemory\Command\Account\ShowCreateUserCommand;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(ShowCreateUserCommand::class)]
#[Small]
final class ShowCreateUserCommandTest extends TestCase
{
    public function testClearsDiagnosticsAnswersTrue(): void
    {
        self::assertTrue((new ShowCreateUserCommand())->clearsDiagnostics());
    }

    public function testExecuteWritesTheStatementOfASystemAccount(): void
    {
        $result = (new Instance())->connect()->query("SHOW CREATE USER 'mysql.sys'@localhost")[0];
        self::assertInstanceOf(ResultSet::class, $result);

        self::assertSame(
            ['CREATE USER for mysql.sys@localhost', 1024, 255, "CREATE USER `mysql.sys`@`localhost` IDENTIFIED WITH 'caching_sha2_password' AS '\$A\$005\$THISISACOMBINATIONOFINVALIDSALTANDPASSWORDTHATMUSTNEVERBRBEUSED' REQUIRE NONE PASSWORD EXPIRE DEFAULT ACCOUNT LOCK PASSWORD HISTORY DEFAULT PASSWORD REUSE INTERVAL DEFAULT PASSWORD REQUIRE CURRENT DEFAULT"],
            [$result->columns[0]->name, $result->columns[0]->length, $result->columns[0]->charset, $result->rows[0][0]],
        );
    }

    public function testExecuteRefusesAMissingAccount(): void
    {
        $session = (new Instance())->connect();

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1396);
        $this->expectExceptionMessage("Operation SHOW CREATE USER failed for 'b'@'%'");

        $session->query('SHOW CREATE USER b');
    }
}
