<?php

declare(strict_types=1);

namespace Tests\Unit\Command\Account;

use MySqlMemory\Account\Identity;
use MySqlMemory\Command\Account\CreateUserCommand;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Instance;
use MySqlMemory\Result\Completion;
use MySqlMemory\Result\ResultSet;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(CreateUserCommand::class)]
#[Small]
final class CreateUserCommandTest extends TestCase
{
    #[\PHPUnit\Framework\Attributes\TestWith(['missing', 1524])]
    #[\PHPUnit\Framework\Attributes\TestWith(['caching_sha2_password', 1827])]
    public function testAbsentChecksExplicitAuthenticationStringsBeforeSkippingExistingAccounts(string $plugin, int $code): void
    {
        $session = (new Instance())->connect();
        $reply = $session->run('CREATE USER IF NOT EXISTS CURRENT_USER IDENTIFIED WITH ' . $plugin . " AS 'bad'")[0];
        self::assertInstanceOf(SqlError::class, $reply);
        self::assertSame($code, $reply->getCode());
        self::assertSame([$code], array_column($session->diagnostics->conditions, 1));
    }

    public function testAbsentResolvesAnExplicitPasswordPluginBeforeExistence(): void
    {
        $session = (new Instance())->connect();
        $this->expectExceptionCode(1524);
        $session->query("CREATE USER IF NOT EXISTS CURRENT_USER IDENTIFIED WITH missing BY 'p'");
    }

    public function testExecuteSkipsDefaultRolesWhenAllUsersAlreadyExist(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE USER existing');
        $reply = $session->query('CREATE USER IF NOT EXISTS existing DEFAULT ROLE missing')[0];

        self::assertSame(1, $reply->warnings());
        self::assertSame([], $session->instance->accounts->defaults);
    }

    public function testAbsentRecordsEveryCredentialFailureAndExistingAccountInOrder(): void
    {
        $session = (new Instance())->connect();

        $reply = $session->run('CREATE USER IF NOT EXISTS a IDENTIFIED WITH missing, CURRENT_USER, b IDENTIFIED WITH missing2')[0];

        self::assertInstanceOf(SqlError::class, $reply);
        self::assertSame(1524, $reply->getCode());
        self::assertSame([1524, 3163, 1524], array_column($session->diagnostics->conditions, 1));
        self::assertNull($session->instance->accounts->find(new Identity('a', '%')));
    }

    public function testAbsentKeepsGrantConversionsInRecipientOrderWhenTheBatchRollsBack(): void
    {
        $session = (new Instance())->connect();
        $reply = $session->run("CREATE USER before_user@'h猫' IDENTIFIED BY 'p', refused IDENTIFIED WITH missing, after_user@'h猫'")[0];

        self::assertInstanceOf(SqlError::class, $reply);
        self::assertSame([...[1366, 1366, 1366, 1366, 1366, 1366, 1366, 1366, 1366], 1524, ...[1366, 1366, 1366, 1366, 1366, 1366, 1366, 1366]], array_column($session->diagnostics->conditions, 1));
        self::assertNull($session->instance->accounts->find(Identity::of('before_user', 'h猫')));
        self::assertNull($session->instance->accounts->find(Identity::of('after_user', 'h猫')));
    }

    public function testFailuresReloadsPersistedHostNamesAfterAnAccountBatchFails(): void
    {
        $session = (new Instance())->connect();
        $session->query("CREATE USER 'u'@'猫'");
        $reply = $session->run("CREATE USER 'u'@'猫'")[0];

        self::assertInstanceOf(SqlError::class, $reply);
        self::assertSame(1396, $reply->getCode());
        self::assertNull($session->instance->accounts->find(Identity::of('u', '猫')));
        self::assertNotNull($session->instance->accounts->find(Identity::of('u', '?')));
    }

    public function testRecipientChecksAttributesForNewAccounts(): void
    {
        $session = (new Instance())->connect();
        $statement = $session->analyze("CREATE USER x ATTRIBUTE 'bad'")->statement;
        assert($statement instanceof \SqlSemantics\Platform\MySql\Statement\Account\CreateUser);
        $this->expectException(SqlError::class);
        $this->expectExceptionCode(3982);
        (new CreateUserCommand())->recipient($statement, Identity::of('x', null), false, $session);
    }

    public function testAbsentRecordsAnAttributeFailureAfterEarlierCredentialFailures(): void
    {
        $session = (new Instance())->connect();
        $error = $session->run("CREATE USER x IDENTIFIED WITH missing, y IDENTIFIED BY 'p', z IDENTIFIED WITH absent ATTRIBUTE 'bad'")[0];
        self::assertInstanceOf(SqlError::class, $error);
        self::assertSame([1524, [1524, 3982]], [$error->getCode(), array_column($session->diagnostics->conditions, 1)]);
        self::assertNull($session->instance->accounts->find(Identity::of('y', null)));
    }

    public function testAbsentIgnoresReplacementCredentialsForAnExistingAccount(): void
    {
        $session = (new Instance())->connect();

        $reply = $session->query('CREATE USER IF NOT EXISTS CURRENT_USER IDENTIFIED WITH missing')[0];

        self::assertInstanceOf(Completion::class, $reply);
        self::assertSame([3163], array_column($session->diagnostics->conditions, 1));
    }

    public function testAbsentValidatesInitialAuthenticationBeforeSkippingExistingAccounts(): void
    {
        $session = (new Instance())->connect();
        $error = $session->run("CREATE USER IF NOT EXISTS CURRENT_USER, CURRENT_USER IDENTIFIED WITH missing INITIAL AUTHENTICATION IDENTIFIED WITH SESSION AS X'0f'")[0];

        self::assertInstanceOf(SqlError::class, $error);
        self::assertSame([1524, "Plugin 'SESSION' is not loaded", [3163, 1524]], [$error->getCode(), $error->getMessage(), array_column($session->diagnostics->conditions, 1)]);
    }

    public function testAbsentDefersRegistrationCredentialsAfterValidInitialAuthentication(): void
    {
        $session = (new Instance())->connect();
        $reply = $session->query("CREATE USER IF NOT EXISTS CURRENT_USER IDENTIFIED WITH missing INITIAL AUTHENTICATION IDENTIFIED BY 'p'")[0];

        self::assertInstanceOf(Completion::class, $reply);
        self::assertSame([3163], array_column($session->diagnostics->conditions, 1));
    }

    public function testCreateRefusesInitialAuthenticationForBuiltInPlugins(): void
    {
        $session = (new Instance())->connect();

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(4054);

        $session->query("CREATE USER u IDENTIFIED WITH caching_sha2_password INITIAL AUTHENTICATION IDENTIFIED BY 'x'");
    }

    public function testExecuteResolvesTheInitialPluginBeforeTheAccountButDefersTheRegistrationPlugin(): void
    {
        $session = (new Instance())->connect();

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1396);

        $session->query("CREATE USER CURRENT_USER IDENTIFIED WITH missing INITIAL AUTHENTICATION IDENTIFIED BY 'x'");
    }

    public function testClearsDiagnosticsAnswersTrue(): void
    {
        self::assertTrue((new CreateUserCommand())->clearsDiagnostics());
    }

    public function testExecuteCreatesAnAccountWithNoAffectedRows(): void
    {
        $session = (new Instance())->connect();

        $reply = $session->query("CREATE USER u IDENTIFIED BY 'x'")[0];

        self::assertInstanceOf(Completion::class, $reply);
        self::assertSame([0, 'x'], [$reply->affectedRows, $session->instance->accounts->find(Identity::of('u', null))?->password]);
    }

    public function testExecuteRefusesExistingAccountsAndCreatesNone(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE USER a');

        $error = $session->run('CREATE USER b, a, root')[0];

        self::assertInstanceOf(SqlError::class, $error);
        self::assertSame([1396, "Operation CREATE USER failed for 'a'@'%','root'@'%'", null], [$error->getCode(), $error->getMessage(), $session->instance->accounts->find(Identity::of('b', null))]);
    }

    public function testExecuteNotesExistingAccountsIfNotExists(): void
    {
        $session = (new Instance())->connect();

        $session->query('CREATE USER IF NOT EXISTS root, b@LocalHost');

        $warnings = $session->query('SHOW WARNINGS')[0];
        self::assertInstanceOf(ResultSet::class, $warnings);
        self::assertSame([['Note', '3163', "Authorization ID 'root'@'%' already exists."]], $warnings->rows);
    }

    public function testExecuteRefusesAMissingDefaultRole(): void
    {
        $session = (new Instance())->connect();

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(3162);
        $this->expectExceptionMessage('Authorization ID `r`@`%` does not exist.');

        $session->query('CREATE USER u DEFAULT ROLE r');
    }

    public function testExecuteChecksThePluginBeforeTheAccount(): void
    {
        $session = (new Instance())->connect();

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1524);
        $this->expectExceptionMessage("Plugin 'nosuch' is not loaded");

        $session->query('CREATE USER root IDENTIFIED WITH nosuch');
    }

    public function testExecuteAnswersARandomPassword(): void
    {
        $session = (new Instance())->connect();

        $reply = $session->query('CREATE USER u IDENTIFIED BY RANDOM PASSWORD')[0];

        self::assertInstanceOf(ResultSet::class, $reply);
        self::assertSame(['u', '%', 20, '1'], [$reply->rows[0][0], $reply->rows[0][1], strlen((string) $reply->rows[0][2]), $reply->rows[0][3]]);
    }

    public function testExecuteWarnsOfAHostTheGrantTablesCannotHold(): void
    {
        $session = (new Instance())->connect();

        $session->query("CREATE USER u@'éx'");

        $warnings = $session->query('SHOW WARNINGS')[0];
        self::assertInstanceOf(ResultSet::class, $warnings);
        self::assertSame([8, ['Warning', '1366', "Incorrect string value: '\\xC3\\xA9x' for column 'Host' at row 1"]], [count($warnings->rows), $warnings->rows[0]]);
    }

    public function testRolesWarnsDuringLookupEvenWhenTheRoleAlreadyExists(): void
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

    public function testAbsentRefusesAnAccountNamedTwice(): void
    {
        $session = (new Instance())->connect();

        $error = $session->run('CREATE USER a, b, a')[0];

        self::assertInstanceOf(SqlError::class, $error);
        self::assertSame([1396, "Operation CREATE USER failed for 'a'@'%'", null], [$error->getCode(), $error->getMessage(), $session->instance->accounts->find(Identity::of('b', null))]);
    }

    public function testDefaultsAnswersTheRoles(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE ROLE r');

        $session->query('CREATE USER u DEFAULT ROLE r');

        $created = $session->query('SHOW CREATE USER u')[0];
        self::assertInstanceOf(ResultSet::class, $created);
        self::assertSame("CREATE USER `u`@`%` IDENTIFIED WITH 'caching_sha2_password' DEFAULT ROLE `r`@`%` REQUIRE NONE PASSWORD EXPIRE DEFAULT ACCOUNT UNLOCK PASSWORD HISTORY DEFAULT PASSWORD REUSE INTERVAL DEFAULT PASSWORD REQUIRE CURRENT DEFAULT", $created->rows[0][0]);
    }

    public function testCreateAppliesTheOptions(): void
    {
        $session = (new Instance())->connect();

        $session->query("CREATE USER u REQUIRE SSL WITH MAX_USER_CONNECTIONS 3 PASSWORD EXPIRE INTERVAL 5 DAY ACCOUNT LOCK COMMENT 'c'");

        $created = $session->query('SHOW CREATE USER u')[0];
        self::assertInstanceOf(ResultSet::class, $created);
        self::assertSame("CREATE USER `u`@`%` IDENTIFIED WITH 'caching_sha2_password' REQUIRE SSL WITH MAX_USER_CONNECTIONS 3 PASSWORD EXPIRE INTERVAL 5 DAY ACCOUNT LOCK PASSWORD HISTORY DEFAULT PASSWORD REUSE INTERVAL DEFAULT PASSWORD REQUIRE CURRENT DEFAULT ATTRIBUTE '{\"comment\": \"c\"}'", $created->rows[0][0]);
    }

    public function testRolesCreatesLockedRoles(): void
    {
        $session = (new Instance())->connect();

        $session->query('CREATE ROLE r');

        $created = $session->query('SHOW CREATE USER r')[0];
        self::assertInstanceOf(ResultSet::class, $created);
        self::assertSame("CREATE USER `r`@`%` IDENTIFIED WITH 'caching_sha2_password' REQUIRE NONE PASSWORD EXPIRE ACCOUNT LOCK PASSWORD HISTORY DEFAULT PASSWORD REUSE INTERVAL DEFAULT PASSWORD REQUIRE CURRENT DEFAULT", $created->rows[0][0]);
    }

    public function testRolesRefusesTheAnonymousUser(): void
    {
        $session = (new Instance())->connect();

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1396);
        $this->expectExceptionMessage('Operation CREATE ROLE failed for anonymous user');

        $session->query("CREATE ROLE ''");
    }

    public function testRolesNotesARoleNamedTwiceIfNotExists(): void
    {
        $session = (new Instance())->connect();

        $session->query('CREATE ROLE IF NOT EXISTS r, r');

        $warnings = $session->query('SHOW WARNINGS')[0];
        self::assertInstanceOf(ResultSet::class, $warnings);
        self::assertSame([['Note', '3163', "Authorization ID 'r'@'%' already exists."]], $warnings->rows);
    }

    public function testLegacyFollowsARefusedHashWithTheFailureOfMySql56(): void
    {
        $session = (new Instance('5.6.51'))->connect();

        $session->run("CREATE USER u IDENTIFIED BY PASSWORD 'abc'");

        $warnings = $session->query('SHOW WARNINGS')[0];
        self::assertInstanceOf(ResultSet::class, $warnings);
        self::assertSame([
            ['Error', '1827', "The password hash doesn't have the expected format. Check if the correct password algorithm is being used with the PASSWORD() function."],
            ['Error', '1396', "Operation CREATE USER failed for 'u'@'%'"],
        ], $warnings->rows);
    }

    public function testDeprecatedWarnsOfIdentifiedByPasswordInMySql57(): void
    {
        $session = (new Instance('5.7.44'))->connect();

        $session->query("CREATE USER u IDENTIFIED BY PASSWORD '*7B9EBEED26AA52ED10C0F549FA863F13C39E0209'");

        $warnings = $session->query('SHOW WARNINGS')[0];
        self::assertInstanceOf(ResultSet::class, $warnings);
        self::assertSame([['Warning', '1287', "'IDENTIFIED BY PASSWORD' is deprecated and will be removed in a future release. Please use IDENTIFIED WITH <plugin> AS <hash> instead"]], $warnings->rows);
    }

    public function testLegacyCreatesTheOtherAccountsWhenOneExistsInMySql57(): void
    {
        $session = (new Instance('5.7.44'))->connect();
        $session->query('CREATE USER b');

        $session->run('CREATE USER a, b, c');

        self::assertSame([true, true], [$session->instance->accounts->find(new Identity('a', '%')) !== null, $session->instance->accounts->find(new Identity('c', '%')) !== null]);
    }
    public function testExecuteUsesTheSessionRandomPasswordLength(): void
    {
        $session = (new Instance())->connect();
        $session->query('SET generated_random_password_length = 255');

        $reply = $session->query('CREATE USER random_user IDENTIFIED BY RANDOM PASSWORD')[0];

        self::assertInstanceOf(ResultSet::class, $reply);
        self::assertIsString($reply->rows[0][2]);
        self::assertSame(255, strlen($reply->rows[0][2]));
        self::assertSame($reply->rows[0][2], $session->instance->accounts->find(new Identity('random_user', '%'))?->password);
    }

    public function testAbsentRecordsInitialCredentialConversionBeforeThePrimaryPluginRefusal(): void
    {
        $session = (new Instance('8.4.7'))->connect();
        $error = $session->run("CREATE USER u@'é' IDENTIFIED WITH missing INITIAL AUTHENTICATION IDENTIFIED BY 'p'")[0];
        self::assertInstanceOf(SqlError::class, $error);
        self::assertSame(1524, $error->getCode());
        self::assertSame([1366, 1524], array_column($session->diagnostics->conditions, 1));
    }

    public function testAbsentRecordsPrimaryConversionBeforeTheAdditionalFactorPolicy(): void
    {
        $session = (new Instance('8.4.7'))->connect();
        $error = $session->run("CREATE USER u@'é' IDENTIFIED BY 'p' AND IDENTIFIED BY RANDOM PASSWORD")[0];
        self::assertInstanceOf(SqlError::class, $error);
        self::assertSame(1524, $error->getCode());
        self::assertSame([1366, 4058, 1524], array_column($session->diagnostics->conditions, 1));
    }
}
