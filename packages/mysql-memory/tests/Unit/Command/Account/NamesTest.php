<?php

declare(strict_types=1);

namespace Tests\Unit\Command\Account;

use MySqlMemory\Account\Identity;
use MySqlMemory\Command\Account\Names;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Instance;
use MySqlMemory\Session\Diagnostics;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Statement\Account\User\SessionUser;
use SqlSemantics\Platform\MySql\Statement\Account\User\UserSpecification;
use SqlSemantics\Platform\MySql\Statement\Name\AccountName;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\Server\ShowGrants;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(Names::class)]
#[Small]
final class NamesTest extends TestCase
{
    public function testReadEmitsOperandWarningsOnlyOnInitialReading(): void
    {
        $session = (new Instance())->connect();
        $names = [new AccountName(new Name('full', \SqlSemantics\Statement\Identifier\NameQuote::Bare)), new \SqlSemantics\Platform\MySql\Statement\Name\CurrentUser()];
        (new Names())->read($names, $session);
        self::assertSame([4119], array_column($session->diagnostics->conditions, 1));
        (new Names())->read($names, $session, false);
        self::assertSame([4119], array_column($session->diagnostics->conditions, 1));
    }

    public function testOwnsTheAccountStatements(): void
    {
        self::assertTrue(Names::owns(new ShowGrants()));
    }

    public function testCheckRefusesAUserNameOfMoreThan32Characters(): void
    {
        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1470);
        $this->expectExceptionMessage("String '" . str_repeat('u', 33) . "' is too long for user name (should be no longer than 32)");

        (new Instance())->connect()->query("CREATE USER '" . str_repeat('u', 33) . "'");
    }

    public function testCheckRefusesAHostOfMoreThan255Bytes(): void
    {
        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1470);
        $this->expectExceptionMessage("String 'x" . str_repeat('é', 34) . "?' is too long for host name (should be no longer than 255)");

        (new Instance())->connect()->query("CREATE USER 'a'@'x" . str_repeat('é', 128) . "'");
    }

    public function testCheckRefusesAHostWithAnAtSign(): void
    {
        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1105);
        $this->expectExceptionMessage("Malformed hostname (illegal symbol: '@')");

        (new Instance())->connect()->query("CREATE USER 'a'@'a@b', '" . str_repeat('u', 40) . "'");
    }

    public function testCheckMeasuresTheTrimmedOperands(): void
    {
        $name = new AccountName(new Name("\r" . str_repeat('u', 32) . "\t"), new Name("\n" . str_repeat('H', 255) . "\v"));
        $names = new Names();

        $names->check([$name]);

        self::assertSame(str_repeat('u', 32) . "\0" . str_repeat('h', 255), $names->identity($name, (new Instance())->connect())->key());
    }

    public function testCheckQuotesTheTrimmedLongOperand(): void
    {
        $this->expectException(SqlError::class);
        $this->expectExceptionMessage("String '" . str_repeat('X', 70) . "' is too long for user name (should be no longer than 32)");

        (new Instance())->connect()->query("CREATE USER '\r" . str_repeat('X', 80) . "'");
    }

    public function testCheckMeasuresTheOriginalQualifiedUserInMySql56(): void
    {
        $this->expectException(SqlError::class);
        $this->expectExceptionMessage("String ' " . str_repeat('u', 16) . " ' is too long for user name (should be no longer than 16)");

        (new Names(GrammarRelease::MySql5651))->check([new AccountName(new Name(' ' . str_repeat('u', 16) . ' '), new Name('%'))]);
    }

    public function testCheckMeasuresTheOriginalExplicitHostInMySql56(): void
    {
        $this->expectException(SqlError::class);
        $this->expectExceptionMessage("String ' " . str_repeat('H', 60) . " ' is too long for host name (should be no longer than 60)");

        (new Names(GrammarRelease::MySql5651))->check([new AccountName(new Name('u'), new Name(' ' . str_repeat('H', 60) . ' '))]);
    }

    public function testCutKeepsSeventyBytes(): void
    {
        self::assertSame([str_repeat('h', 70), 'short'], [(new Names())->cut(str_repeat('h', 300)), (new Names())->cut('short')]);
    }

    public function testUsersAnswersTheAccountOfEachAlteration(): void
    {
        $name = new AccountName(new Name('a'));

        self::assertSame([$name], (new Names())->users([new UserSpecification($name)]));
    }

    public function testIdentityAnswersTheAccountOfTheSessionForUser(): void
    {
        self::assertSame("app\0%", (new Names())->identity(new SessionUser(), (new Instance())->connect('app'))->key());
    }

    public function testIdentityRetainsRenamedUserAndHostSeparately(): void
    {
        $session = (new Instance())->connect('login');
        $session->variables->definer = new Identity('renamed@user', 'host');

        self::assertSame("renamed@user\0host", (new Names())->identity(new \SqlSemantics\Platform\MySql\Statement\Name\CurrentUser(), $session)->key());
        self::assertSame('login', $session->user);
    }

    public function testAsciiWarnsForEachGrantTable(): void
    {
        $diagnostics = new Diagnostics();

        (new Names())->ascii(new Identity('u', 'a`a`éé "\' \\\\00'), 2, $diagnostics);

        self::assertSame([['Warning', 1366, "Incorrect string value: '\\xC3\\xA9\\xC3\\xA9 \"...' for column 'Host' at row 1"], ['Warning', 1366, "Incorrect string value: '\\xC3\\xA9\\xC3\\xA9 \"...' for column 'Host' at row 1"]], $diagnostics->conditions);
    }

    public function testAsciiPreservesLegacyUnicodeHostsWithoutWarnings(): void
    {
        $diagnostics = new Diagnostics();
        (new Names(GrammarRelease::MySql5651))->ascii(new Identity('u', '猫'), 7, $diagnostics);
        (new Names(GrammarRelease::MySql5744))->ascii(new Identity('u', '猫'), 7, $diagnostics);

        self::assertSame([], $diagnostics->conditions);
    }

    public function testFailureBoundsTheDisplayedAccountList(): void
    {
        $error = (new Names())->failure('DROP USER', "'u'@'" . str_repeat('h', 255) . "'");

        self::assertSame(1396, $error->getCode());
        self::assertSame("Operation DROP USER failed for 'u'@'" . str_repeat('h', 251), $error->getMessage());
    }

    public function testRefusalKeepsTheTypedLimitAndDecodedDiagnosticOperand(): void
    {
        $names = new Names(GrammarRelease::MySql5651);
        $length = $names->refusal(new \SqlSemantics\Platform\MySql\Statement\Account\Problem\AccountNameFailure("user\0rest", false, 16));
        $host = $names->refusal(new \SqlSemantics\Platform\MySql\Statement\Account\Problem\AccountNameFailure('a@b', true));

        self::assertSame([1470, "String 'user' is too long for user name (should be no longer than 16)"], [$length->getCode(), $length->getMessage()]);
        self::assertSame([1105, "Malformed hostname (illegal symbol: '@')"], [$host->getCode(), $host->getMessage()]);
    }

    public function testResolveWarnsOfAHostName(): void
    {
        $diagnostics = new Diagnostics();

        (new Names())->resolve(new Identity('u', '%'), $diagnostics);
        (new Names())->resolve(new Identity('u', '127.0.0.1'), $diagnostics);
        (new Names())->resolve(new Identity('u', '::1'), $diagnostics);
        (new Names())->resolve(new Identity('u', 'h_st'), $diagnostics);
        (new Names())->resolve(new Identity('u', ''), $diagnostics);
        (new Names())->resolve(new Identity('u', '10.0.0.0/255.0.0.0'), $diagnostics);
        (new Names())->resolve(new Identity('u', 'cafe'), $diagnostics);

        self::assertCount(1, $diagnostics->conditions);
    }

    public function testLimitsAnswerTheLongestNamesOfTheRelease(): void
    {
        self::assertSame([[16, 60], [32, 60], [32, 255]], [(new Names(GrammarRelease::MySql5651))->limits(), (new Names(GrammarRelease::MySql5744))->limits(), (new Names())->limits()]);
    }

    public function testResolveDoesNotWarnOfLocalhostInMySql56(): void
    {
        $diagnostics = new Diagnostics();

        (new Names(GrammarRelease::MySql5651))->resolve(new Identity('u', 'localhost'), $diagnostics);
        (new Names(GrammarRelease::MySql5744))->resolve(new Identity('u', 'localhost'), $diagnostics);

        self::assertCount(1, $diagnostics->conditions);
    }

    public function testExistenceNamesAUserInMySql57(): void
    {
        self::assertSame("User 'a'@'%' does not exist.", (new Names(GrammarRelease::MySql5744))->existence(new Identity('a', '%'), false));
    }
}
