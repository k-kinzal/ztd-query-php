<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Configuration\Account;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Model\Configuration\Account\ClientAccount;

#[CoversClass(ClientAccount::class)]
#[Medium]
final class ClientAccountTest extends TestCase
{
    public function testCasesSpellTheClientAccountFunction(): void
    {
        self::assertSame(['USER()'], array_column(ClientAccount::cases(), 'value'));
    }
}
