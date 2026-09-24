<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\Account;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Model\Configuration\Account\AccountName;
use SqlSemantics\Model\Definition\Account\Identification\RandomPassword;
use SqlSemantics\Model\Definition\Account\InitialAuthenticationDefinition;
use SqlSemantics\Model\Validation\InvalidStructure;

#[CoversClass(InitialAuthenticationDefinition::class)]
#[Medium]
final class InitialAuthenticationDefinitionTest extends TestCase
{
    public function testKeepsThePasswordlessPluginAndTemporaryCredential(): void
    {
        $definition = new InitialAuthenticationDefinition(new AccountName('u'), 'authentication_webauthn', RandomPassword::Generated);
        self::assertSame('authentication_webauthn', $definition->plugin);
        self::assertSame(RandomPassword::Generated, $definition->initialAuthentication);
    }

    public function testRejectsAnEmptyPluginName(): void
    {
        $this->expectException(InvalidStructure::class);
        new InitialAuthenticationDefinition(new AccountName('u'), '', RandomPassword::Generated);
    }
}
