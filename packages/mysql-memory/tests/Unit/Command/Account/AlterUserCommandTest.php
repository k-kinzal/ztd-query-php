<?php

declare(strict_types=1);

namespace Tests\Unit\Command\Account;

use MySqlMemory\Account\Account;
use MySqlMemory\Account\Identity;
use MySqlMemory\Command\Account\AlterUserCommand;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(AlterUserCommand::class)]
#[Small]
final class AlterUserCommandTest extends TestCase
{
    public function testMissingKeepsChangesOfExistingLegacyAccounts(): void
    {
        $session = (new Instance('5.7.44'))->connect();
        $session->query('ALTER USER CURRENT_USER PASSWORD EXPIRE');
        $answers = $session->run("ALTER USER missing IDENTIFIED BY 'new', CURRENT_USER IDENTIFIED BY 'changed'");

        self::assertInstanceOf(SqlError::class, $answers[0]);
        self::assertSame(1396, $answers[0]->getCode());
        self::assertSame('changed', $session->instance->accounts->find(new Identity('root', '%'))?->password);
        self::assertFalse($session->passwordExpired);
    }

    public function testMissingLeavesModernAccountsUnchanged(): void
    {
        $session = (new Instance())->connect();
        $session->query("SET PASSWORD='original'");
        $answers = $session->run("ALTER USER CURRENT_USER IDENTIFIED BY 'changed', missing IDENTIFIED BY 'new'");

        self::assertInstanceOf(SqlError::class, $answers[0]);
        self::assertSame(1396, $answers[0]->getCode());
        self::assertSame('original', $session->instance->accounts->find(new Identity('root', '%'))?->password);
    }

    public function testClearsDiagnosticsAnswersTrue(): void
    {
        self::assertTrue((new AlterUserCommand())->clearsDiagnostics());
    }

    public function testExpireKeepsConnectedLegacySessionsUnrestricted(): void
    {
        $instance = new Instance('5.6.51');
        $session = $instance->connect();
        $session->query('ALTER USER CURRENT_USER PASSWORD EXPIRE');
        self::assertTrue($instance->accounts->find(new Identity('root', '%'))?->expired);
        self::assertFalse($session->passwordExpired);
        self::assertCount(1, $session->query('SELECT 1'));
    }

    public function testExecuteRefusesMissingAccounts(): void
    {
        $session = (new Instance())->connect();

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1396);
        $this->expectExceptionMessage("Operation ALTER USER failed for 'nobody'@'%','nobody2'@'%'");

        $session->query('ALTER USER nobody, root, nobody2 ACCOUNT LOCK');
    }

    public function testExecuteNotesMissingAccountsIfExists(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE USER u');

        $session->query('ALTER USER IF EXISTS nobody, u ACCOUNT LOCK');

        $warnings = $session->query('SHOW WARNINGS')[0];
        self::assertInstanceOf(ResultSet::class, $warnings);
        self::assertSame([[['Note', '3162', "Authorization ID 'nobody'@'%' does not exist."]], true], [$warnings->rows, $session->instance->accounts->find(new Identity('u', '%'))?->locked]);
    }

    public function testExecuteExpiresThePasswordOfAPluginNamedAlone(): void
    {
        $session = (new Instance())->connect();
        $session->query("CREATE USER u IDENTIFIED BY 'x'");

        $session->query("ALTER USER u IDENTIFIED WITH 'sha256_password'");

        $created = $session->query('SHOW CREATE USER u')[0];
        self::assertInstanceOf(ResultSet::class, $created);
        self::assertSame("CREATE USER `u`@`%` IDENTIFIED WITH 'sha256_password' REQUIRE NONE PASSWORD EXPIRE ACCOUNT UNLOCK PASSWORD HISTORY DEFAULT PASSWORD REUSE INTERVAL DEFAULT PASSWORD REQUIRE CURRENT DEFAULT", $created->rows[0][0]);
    }

    public function testAuthenticationRefusesAFactorOfALoadedPlugin(): void
    {
        $session = (new Instance())->connect();

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(4052);
        $this->expectExceptionMessage('Invalid plugin "caching_sha2_password" specified as 2 factor during "ALTER USER".');

        $session->query("ALTER USER nobody ADD 2 FACTOR IDENTIFIED WITH caching_sha2_password BY 'x'");
    }

    public function testFactorsWarnsBeforeLookingUpAnOmittedPlugin(): void
    {
        $session = (new Instance())->connect();
        $answers = $session->run("ALTER USER CURRENT_USER ADD 2 FACTOR IDENTIFIED BY 'x'");

        self::assertInstanceOf(SqlError::class, $answers[0]);
        self::assertSame([4058, 1524], array_column($session->diagnostics->conditions, 1));
    }

    public function testFactorsNeedsTheSecondFactorBeforeAddingTheThird(): void
    {
        $session = (new Instance())->connect();

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(4057);
        $this->expectExceptionMessage("2 factor authentication method doesn't exist.");

        $session->query('ALTER USER CURRENT_USER ADD 3 FACTOR IDENTIFIED WITH missing');
    }

    public function testExecuteRefusesADayOutOfRange(): void
    {
        $session = (new Instance())->connect();

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1525);
        $this->expectExceptionMessage("Incorrect DAY value: '0'");

        $session->query('ALTER USER nobody PASSWORD EXPIRE INTERVAL 0 DAY');
    }

    public function testChangeRefusesAFactorThatDoesNotExist(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE USER u');

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(4057);
        $this->expectExceptionMessage("2 factor authentication method doesn't exist. Please do ALTER USER... ADD 2 factor... before doing this operation.");

        $session->query('ALTER USER u DROP 2 FACTOR');
    }

    public function testChangeMergesTheAttributesAndKeepsNothingOnFailure(): void
    {
        $session = (new Instance())->connect();
        $session->query("CREATE USER u COMMENT 'c'");

        $session->query('ALTER USER u ATTRIBUTE \'{"a": 1}\'');
        $session->run("ALTER USER u ACCOUNT LOCK ATTRIBUTE '[1]'");

        self::assertSame(['{"a": 1, "comment": "c"}', false], [$session->instance->accounts->find(new Identity('u', '%'))?->attributes, $session->instance->accounts->find(new Identity('u', '%'))?->locked]);
    }

    public function testReplaceRefusesAnotherAccount(): void
    {
        $session = (new Instance())->connect();

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(3893);

        (new AlterUserCommand())->replace(new Account(new Identity('u', '%')), '', $session);
    }

    public function testReplaceChecksTheCurrentPasswordOfTheSession(): void
    {
        $session = (new Instance())->connect();

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(3891);

        (new AlterUserCommand())->replace(new Account(new Identity('root', '%'), password: 'root'), 'x', $session);
    }
    public function testExecuteUsesTheSessionRandomPasswordLength(): void
    {
        $session = (new Instance())->connect();
        $session->query('SET generated_random_password_length = 255');

        $reply = $session->query('ALTER USER CURRENT_USER IDENTIFIED BY RANDOM PASSWORD')[0];

        self::assertInstanceOf(ResultSet::class, $reply);
        self::assertIsString($reply->rows[0][2]);
        self::assertSame(255, strlen($reply->rows[0][2]));
        self::assertSame($reply->rows[0][2], $session->instance->accounts->find(new Identity('root', '%'))?->password);
    }

}
