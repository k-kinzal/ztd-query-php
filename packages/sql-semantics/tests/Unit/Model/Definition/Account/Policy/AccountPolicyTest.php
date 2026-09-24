<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\Account\Policy;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Model\Definition\Account\Policy\AccountPolicy;

#[CoversClass(AccountPolicy::class)]
#[Medium]
final class AccountPolicyTest extends TestCase
{
    public function testCasesAreSpelledAsTheirSqlKeywords(): void
    {
        self::assertSame(['ACCOUNT LOCK', 'ACCOUNT UNLOCK', 'PASSWORD EXPIRE', 'PASSWORD EXPIRE NEVER', 'PASSWORD EXPIRE DEFAULT', 'PASSWORD HISTORY DEFAULT', 'PASSWORD REUSE INTERVAL DEFAULT', 'PASSWORD REQUIRE CURRENT', 'PASSWORD REQUIRE CURRENT DEFAULT', 'PASSWORD REQUIRE CURRENT OPTIONAL', 'PASSWORD_LOCK_TIME UNBOUNDED'], array_column(AccountPolicy::cases(), 'value'));
    }
}
