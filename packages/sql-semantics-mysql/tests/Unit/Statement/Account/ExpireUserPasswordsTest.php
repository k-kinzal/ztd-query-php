<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Account;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Account\ExpireUserPasswords;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(ExpireUserPasswords::class)]
#[Medium]
final class ExpireUserPasswordsTest extends TestCase
{
    public function testDeriveStatementRecordsNoFact(): void
    {
        self::assertSame([], (new Inspection('mysql-5.6.51'))->inspect('ALTER USER a PASSWORD EXPIRE')->facts->diagnostics);
    }

    public function testRenderWritesEveryAccount(): void
    {
        self::assertSame('ALTER USER a PASSWORD EXPIRE, CURRENT_USER() PASSWORD EXPIRE', (new Inspection('mysql-5.6.51'))->inspect('alter user a password expire, current_user() password expire')->toString());
    }
}
