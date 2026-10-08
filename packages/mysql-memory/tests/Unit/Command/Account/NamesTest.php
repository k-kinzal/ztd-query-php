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
    public function testOwnsTheAccountStatements(): void
    {
        self::assertTrue(Names::owns(new ShowGrants()));
    }

    public function testCheckRefusesAUserNameOfMoreThan32Characters(): void
    {
        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1470);
        $this->expectExceptionMessage("String '" . str_repeat('u', 33) . "' is too long for user name (should be no longer than 32)");

        (new Names())->check([new AccountName(new Name(str_repeat('u', 33)))]);
    }

    public function testCheckRefusesAHostOfMoreThan255Bytes(): void
    {
        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1470);
        $this->expectExceptionMessage("String 'x" . str_repeat('é', 34) . "?' is too long for host name (should be no longer than 255)");

        (new Names())->check([new AccountName(new Name('a'), new Name('x' . str_repeat('é', 128)))]);
    }

    public function testCheckRefusesAHostWithAnAtSign(): void
    {
        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1105);
        $this->expectExceptionMessage("Malformed hostname (illegal symbol: '@')");

        (new Names())->check([null, new AccountName(new Name('a'), new Name('a@b')), new AccountName(new Name(str_repeat('u', 40)))]);
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

    public function testAsciiWarnsForEachGrantTable(): void
    {
        $diagnostics = new Diagnostics();

        (new Names())->ascii(new Identity('u', 'a`a`éé "\' \\\\00'), 2, $diagnostics);

        self::assertSame([['Warning', 1366, "Incorrect string value: '\\xC3\\xA9\\xC3\\xA9 \"...' for column 'Host' at row 1"], ['Warning', 1366, "Incorrect string value: '\\xC3\\xA9\\xC3\\xA9 \"...' for column 'Host' at row 1"]], $diagnostics->conditions);
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
}
