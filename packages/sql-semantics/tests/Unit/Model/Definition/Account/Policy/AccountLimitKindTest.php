<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\Account\Policy;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Model\Definition\Account\Policy\AccountLimitKind;

#[CoversClass(AccountLimitKind::class)]
#[Medium]
final class AccountLimitKindTest extends TestCase
{
    public function testCasesAreSpelledAsTheirSqlKeywords(): void
    {
        self::assertSame(['PASSWORD EXPIRE INTERVAL', 'PASSWORD HISTORY', 'PASSWORD REUSE INTERVAL', 'FAILED_LOGIN_ATTEMPTS', 'PASSWORD_LOCK_TIME'], array_column(AccountLimitKind::cases(), 'value'));
    }
}
