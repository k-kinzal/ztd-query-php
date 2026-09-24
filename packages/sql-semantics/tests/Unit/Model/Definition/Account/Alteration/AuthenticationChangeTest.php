<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\Account\Alteration;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Model\Configuration\Account\CurrentAccount;
use SqlSemantics\Model\Definition\Account\Alteration\AuthenticationChange;
use SqlSemantics\Model\Definition\Account\Identification\PluginRandomPasswordIdentification;

#[CoversClass(AuthenticationChange::class)]
#[Medium]
final class AuthenticationChangeTest extends TestCase
{
    public function testKeepsTheGeneratedCredentialAndRetention(): void
    {
        $identification = new PluginRandomPasswordIdentification('caching_sha2_password');
        $change = new AuthenticationChange(CurrentAccount::Authenticated, $identification, true);
        self::assertSame($identification, $change->identification);
        self::assertTrue($change->retainCurrentPassword);
        self::assertSame(CurrentAccount::Authenticated, $change->account);
    }
}
