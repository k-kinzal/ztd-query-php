<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\Account\Alteration;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Model\Configuration\Account\CurrentAccount;
use SqlSemantics\Model\Definition\Account\Alteration\AccountTarget;

#[CoversClass(AccountTarget::class)]
#[Medium]
final class AccountTargetTest extends TestCase
{
    public function testKeepsTheAuthenticatedAccountSymbolic(): void
    {
        self::assertSame(CurrentAccount::Authenticated, (new AccountTarget(CurrentAccount::Authenticated))->account);
    }
}
