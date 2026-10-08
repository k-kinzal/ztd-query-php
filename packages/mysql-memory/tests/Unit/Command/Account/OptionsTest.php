<?php

declare(strict_types=1);

namespace Tests\Unit\Command\Account;

use MySqlMemory\Account\Account;
use MySqlMemory\Account\Identity;
use MySqlMemory\Command\Account\Options;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Instance;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Account\Option\AccountOption;
use SqlSemantics\Platform\MySql\Statement\Account\Option\AccountOptionKind;
use SqlSemantics\Platform\MySql\Statement\Account\User\Credential;
use SqlSemantics\Platform\MySql\Statement\Account\User\Identification;
use SqlSemantics\Platform\MySql\Statement\Literal\Numeral;
use SqlSemantics\Platform\MySql\Statement\Literal\Text;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(Options::class)]
#[Small]
final class OptionsTest extends TestCase
{
    public function testParsedQuotesTheStatementFromANumberThatIsNotAnInteger(): void
    {
        $session = (new Instance())->connect();

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1064);
        $this->expectExceptionMessage("Only integers allowed as number here near '1.5 DAY' at line 1");

        $session->query('CREATE USER u PASSWORD EXPIRE INTERVAL 1.5 DAY');
    }

    public function testParsedRefusesATlsPropertyNamedTwice(): void
    {
        $session = (new Instance())->connect();

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1225);
        $this->expectExceptionMessage("Option 'ISSUER' used twice in statement");

        $session->query("CREATE USER root REQUIRE ISSUER 'a' AND ISSUER 'b'");
    }

    public function testCheckRefusesAPluginThatIsNotLoaded(): void
    {
        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1524);

        (new Options())->check(new Identification(new Name('nosuch'), Credential::None), 'caching_sha2_password');
    }

    public function testIdentifyKeepsThePluginAndClearsAnExpiredPassword(): void
    {
        $account = new Account(new Identity('u', '%'), 'sha256_password', expired: true);

        $generated = (new Options())->identify($account, new Identification(null, Credential::Password, new Text('x')), true);

        self::assertSame([null, 'sha256_password', false, 'x', 67], [$generated, $account->plugin, $account->expired, $account->password, strlen($account->hash)]);
    }

    public function testApplySetsTheTlsConditionsInTheirOrder(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE USER u');

        $session->query("ALTER USER u REQUIRE CIPHER 'c' AND SUBJECT 's'");

        self::assertSame(['SUBJECT' => 's', 'CIPHER' => 'c'], $session->instance->accounts->find(new Identity('u', '%'))?->tlsConditions);
    }

    public function testOptionSetsTheLockTime(): void
    {
        $account = new Account(new Identity('u', '%'));

        (new Options())->option($account, new AccountOption(AccountOptionKind::LockTimeUnbounded));

        self::assertSame(-1, $account->lockTime);
    }

    public function testNumberKeepsTheLow32BitsOfASaturatedNumber(): void
    {
        self::assertSame([4294967295, 0, 70000, 15], [(new Options())->number(new Numeral('18446744073709551616')), (new Options())->number(new Numeral('4294967296')), (new Options())->number(new Numeral('70000')), (new Options())->number(new Numeral('0f', true))]);
    }
}
