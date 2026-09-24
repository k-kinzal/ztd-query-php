<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\Account\Alteration;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Model\Configuration\Account\ClientAccount;
use SqlSemantics\Model\Definition\Account\Alteration\OldPasswordDiscard;

#[CoversClass(OldPasswordDiscard::class)]
#[Medium]
final class OldPasswordDiscardTest extends TestCase
{
    public function testKeepsTheConnectingClientAccountSymbolic(): void
    {
        self::assertSame(ClientAccount::Connected, (new OldPasswordDiscard(ClientAccount::Connected))->account);
    }
}
