<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Configuration\Account;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Model\Configuration\Account\CurrentAccount;

#[CoversClass(CurrentAccount::class)]
#[Medium]
final class CurrentAccountTest extends TestCase
{
    public function testFromSelectsAnExplicitAccountOperation(): void
    {
        self::assertSame(CurrentAccount::Authenticated, CurrentAccount::from('CURRENT_USER'));
    }
}
