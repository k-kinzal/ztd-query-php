<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\Account;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Model\Configuration\Account\AccountName;
use SqlSemantics\Model\Definition\Account\AccountDefinition;
use SqlSemantics\Model\Definition\Account\Identification\PluginIdentification;
use SqlSemantics\Model\Definition\Account\Identification\RandomPassword;
use SqlSemantics\Model\Validation\InvalidStructure;

#[CoversClass(AccountDefinition::class)]
#[Medium]
final class AccountDefinitionTest extends TestCase
{
    public function testKeepsAnAccountWithoutCredentials(): void
    {
        $definition = new AccountDefinition(new AccountName('u', 'h'));
        self::assertNull($definition->identification);
        self::assertSame([], $definition->additionalFactors);
    }

    public function testKeepsTheSecondAndThirdFactorsInOrder(): void
    {
        $second = new PluginIdentification('authentication_fido');
        $definition = new AccountDefinition(new AccountName('u'), RandomPassword::Generated, [$second, RandomPassword::Generated]);
        self::assertSame([$second, RandomPassword::Generated], $definition->additionalFactors);
    }

    public function testRejectsAFourthFactor(): void
    {
        $this->expectException(InvalidStructure::class);
        new AccountDefinition(new AccountName('u'), RandomPassword::Generated, [RandomPassword::Generated, RandomPassword::Generated, RandomPassword::Generated]);
    }
}
