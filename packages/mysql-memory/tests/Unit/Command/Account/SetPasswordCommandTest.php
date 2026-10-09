<?php

declare(strict_types=1);

namespace Tests\Unit\Command\Account;

use MySqlMemory\Account\Identity;
use MySqlMemory\Command\Account\SetPasswordCommand;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(SetPasswordCommand::class)]
#[Small]
final class SetPasswordCommandTest extends TestCase
{
    public function testClearsDiagnosticsAnswersTrue(): void
    {
        self::assertTrue((new SetPasswordCommand())->clearsDiagnostics());
    }

    public function testExecuteSetsThePasswordOfTheSession(): void
    {
        $session = (new Instance())->connect();

        $session->query("SET PASSWORD = 'a''b'");

        self::assertSame("a'b", $session->instance->accounts->find(new Identity('root', '%'))?->password);
    }

    public function testExecuteRefusesAMissingAccount(): void
    {
        $session = (new Instance())->connect();

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1133);
        $this->expectExceptionMessage("Can't find any matching row in the user table");

        $session->query("SET PASSWORD FOR nobody = 'x'");
    }

    public function testExecuteRefusesAWrongCurrentPassword(): void
    {
        $session = (new Instance())->connect();
        $session->query("SET PASSWORD = 'root'");

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(3891);
        $this->expectExceptionMessage('Incorrect current password. Specify the correct password which has to be replaced.');

        $session->query("SET PASSWORD = 'x' REPLACE 'wrong' RETAIN CURRENT PASSWORD");
    }

    public function testExecuteRefusesTheCurrentPasswordOfAnotherAccount(): void
    {
        $session = (new Instance())->connect();
        $session->query("CREATE USER u IDENTIFIED BY 'x'");

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(3893);
        $this->expectExceptionMessage('Do not specify the current password while changing it for other users.');

        $session->query("SET PASSWORD FOR u = 'y' REPLACE 'x'");
    }

    public function testExecuteAnswersARandomPassword(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE USER u');

        $reply = $session->query('SET PASSWORD FOR u TO RANDOM')[0];

        self::assertInstanceOf(ResultSet::class, $reply);
        self::assertSame([['user', 16], ['host', 16], ['generated password', 72], ['auth_factor', 13]], array_map(static fn ($column): array => [$column->name, $column->length], $reply->columns));
    }

    public function testExecuteWarnsOfThePasswordFunctionIn57(): void
    {
        $session = (new Instance('5.7.44'))->connect();
        $session->query("CREATE USER 'u'@'h'");
        $session->query("SET PASSWORD FOR 'u'@'h' = PASSWORD('x')");

        self::assertSame([['Warning', 1287, "'SET PASSWORD FOR <user> = PASSWORD('<plaintext_password>')' is deprecated and will be removed in a future release. Please use SET PASSWORD FOR <user> = '<plaintext_password>' instead"]], $session->diagnostics->conditions);
    }

    public function testLegacyTakesAStringAsTheHashInMySql56(): void
    {
        $session = (new Instance('5.6.51'))->connect();
        $session->query('CREATE USER u');

        $session->query("SET PASSWORD FOR u = PASSWORD('z')");

        $password = $session->query("SELECT Password FROM mysql.user WHERE User = 'u'")[0];
        self::assertInstanceOf(ResultSet::class, $password);
        self::assertSame([['*F24059C44AE7FCD38A595267C522FB133E9F06F1']], $password->rows);
        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1372);
        $this->expectExceptionMessage('Password hash should be a 41-digit hexadecimal number');

        $session->query("SET PASSWORD FOR u = 'z'");
    }
}
