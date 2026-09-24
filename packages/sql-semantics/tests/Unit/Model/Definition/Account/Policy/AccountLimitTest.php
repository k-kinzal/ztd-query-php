<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\Account\Policy;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Model\Definition\Account\Policy\AccountLimit;
use SqlSemantics\Model\Definition\Account\Policy\AccountLimitKind;
use SqlSemantics\Model\Validation\InvalidStructure;

#[CoversClass(AccountLimit::class)]
#[Medium]
final class AccountLimitTest extends TestCase
{
    #[TestWith([AccountLimitKind::PasswordExpiryDays, 1])]
    #[TestWith([AccountLimitKind::PasswordExpiryDays, 65535])]
    #[TestWith([AccountLimitKind::FailedLoginAttempts, 0])]
    #[TestWith([AccountLimitKind::PasswordLockDays, 32767])]
    #[TestWith([AccountLimitKind::PasswordHistory, 100000])]
    #[TestWith([AccountLimitKind::PasswordReuseDays, 0])]
    public function testAcceptsTheBoundariesOfEachKind(AccountLimitKind $kind, int $value): void
    {
        $limit = new AccountLimit($kind, $value);
        self::assertSame($kind, $limit->kind);
        self::assertSame($value, $limit->value);
    }

    #[TestWith([AccountLimitKind::PasswordExpiryDays, 0])]
    #[TestWith([AccountLimitKind::PasswordExpiryDays, 65536])]
    #[TestWith([AccountLimitKind::FailedLoginAttempts, 32768])]
    #[TestWith([AccountLimitKind::PasswordLockDays, -1])]
    #[TestWith([AccountLimitKind::PasswordHistory, -1])]
    public function testRejectsValuesOutsideTheRangeOfTheKind(AccountLimitKind $kind, int $value): void
    {
        $this->expectException(InvalidStructure::class);
        new AccountLimit($kind, $value);
    }

    public function testRejectionNamesTheKind(): void
    {
        $this->expectException(InvalidStructure::class);
        $this->expectExceptionMessage('The account policy value is outside the range MySQL accepts for ' . AccountLimitKind::PasswordExpiryDays->value . '.');
        new AccountLimit(AccountLimitKind::PasswordExpiryDays, 0);
    }
}
