<?php

declare(strict_types=1);

namespace Tests\Unit\Account;

use MySqlMemory\Account\Account;
use MySqlMemory\Account\CreateText;
use MySqlMemory\Account\Identity;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Account\Option\TlsKind;

#[CoversClass(CreateText::class)]
#[Small]
final class CreateTextTest extends TestCase
{
    public function testStatementWritesANewAccount(): void
    {
        self::assertSame(
            "CREATE USER `u`@`%` IDENTIFIED WITH 'caching_sha2_password' REQUIRE NONE PASSWORD EXPIRE DEFAULT ACCOUNT UNLOCK PASSWORD HISTORY DEFAULT PASSWORD REUSE INTERVAL DEFAULT PASSWORD REQUIRE CURRENT DEFAULT",
            (new CreateText())->statement(new Account(new Identity('u', '%')), []),
        );
    }

    public function testStatementWritesEveryOption(): void
    {
        $account = new Account(new Identity('u', '%'), 'sha256_password', "\$5\$a'b", null, false, 30, true, 3, 10, false, 3, -1, TlsKind::Ssl, [], ['MAX_QUERIES_PER_HOUR' => 5, 'MAX_UPDATES_PER_HOUR' => 0, 'MAX_CONNECTIONS_PER_HOUR' => 0, 'MAX_USER_CONNECTIONS' => 8], '{"comment": "hi\'x"}');

        self::assertSame(
            "CREATE USER `u`@`%` IDENTIFIED WITH 'sha256_password' AS '\$5\$a\\'b' DEFAULT ROLE `r`@`%` REQUIRE SSL WITH MAX_QUERIES_PER_HOUR 5 MAX_USER_CONNECTIONS 8 PASSWORD EXPIRE INTERVAL 30 DAY ACCOUNT LOCK PASSWORD HISTORY 3 PASSWORD REUSE INTERVAL 10 DAY PASSWORD REQUIRE CURRENT OPTIONAL FAILED_LOGIN_ATTEMPTS 3 PASSWORD_LOCK_TIME UNBOUNDED ATTRIBUTE '{\"comment\": \"hi\\'x\"}'",
            (new CreateText())->statement($account, [new Identity('r', '%')]),
        );
    }

    public function testTlsWritesTheConditionsUnquoted(): void
    {
        $account = new Account(new Identity('u', '%'), tls: TlsKind::Specified, tlsConditions: ['SUBJECT' => 'sub', 'ISSUER' => "is's", 'CIPHER' => 'ci']);

        self::assertSame("SUBJECT 'sub' ISSUER 'is's' CIPHER 'ci'", (new CreateText())->tls($account));
    }
}
