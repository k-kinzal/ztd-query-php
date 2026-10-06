<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Account;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Account\ExpireUserPasswords;

#[CoversClass(ExpireUserPasswords::class)]
#[Medium]
final class ExpireUserPasswordsTest extends TestCase
{
    public function testDeriveStatementRecordsNoFact(): void
    {
        self::assertSame([], (new Semantics(Dialect::MySql, 'mysql-5.6.51'))->analyze('ALTER USER a PASSWORD EXPIRE')->facts->diagnostics);
    }

    public function testRenderWritesEveryAccount(): void
    {
        self::assertSame('ALTER USER a PASSWORD EXPIRE, CURRENT_USER() PASSWORD EXPIRE', (new Semantics(Dialect::MySql, 'mysql-5.6.51'))->analyze('alter user a password expire, current_user() password expire')->toString());
    }
}
