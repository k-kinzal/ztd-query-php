<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\Account\Alteration;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Model\Configuration\Account\AccountName;
use SqlSemantics\Model\Definition\Account\Alteration\AuthenticationFactor;
use SqlSemantics\Model\Definition\Account\Alteration\FactorChange;
use SqlSemantics\Model\Definition\Account\Alteration\FactorIdentification;
use SqlSemantics\Model\Definition\Account\Alteration\FactorOperation;
use SqlSemantics\Model\Definition\Account\Identification\PluginIdentification;
use SqlSemantics\Model\Definition\Account\Identification\RandomPassword;
use SqlSemantics\Model\Validation\InvalidStructure;

#[CoversClass(FactorChange::class)]
#[Medium]
final class FactorChangeTest extends TestCase
{
    public function testModifyAcceptsFactorsInAnyOrder(): void
    {
        $change = new FactorChange(new AccountName('u'), FactorOperation::Modify, [new FactorIdentification(AuthenticationFactor::Third, RandomPassword::Generated), new FactorIdentification(AuthenticationFactor::Second, new PluginIdentification('p'))]);
        self::assertSame(FactorOperation::Modify, $change->operation);
        self::assertSame(AuthenticationFactor::Third, $change->factors[0]->factor);
    }

    public function testAddRejectsDescendingFactors(): void
    {
        $this->expectException(InvalidStructure::class);
        new FactorChange(new AccountName('u'), FactorOperation::Add, [new FactorIdentification(AuthenticationFactor::Third, RandomPassword::Generated), new FactorIdentification(AuthenticationFactor::Second, RandomPassword::Generated)]);
    }

    public function testRejectsTheSameFactorTwice(): void
    {
        $this->expectException(InvalidStructure::class);
        new FactorChange(new AccountName('u'), FactorOperation::Modify, [new FactorIdentification(AuthenticationFactor::Second, RandomPassword::Generated), new FactorIdentification(AuthenticationFactor::Second, RandomPassword::Generated)]);
    }

    public function testRejectsMoreThanTwoFactors(): void
    {
        $factor = new FactorIdentification(AuthenticationFactor::Second, RandomPassword::Generated);
        $this->expectException(InvalidStructure::class);
        new FactorChange(new AccountName('u'), FactorOperation::Modify, [$factor, new FactorIdentification(AuthenticationFactor::Third, RandomPassword::Generated), $factor]);
    }
}
