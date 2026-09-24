<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\Account\Alteration;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Model\Configuration\Account\CurrentAccount;
use SqlSemantics\Model\Definition\Account\Alteration\AuthenticationFactor;
use SqlSemantics\Model\Definition\Account\Alteration\FactorRemoval;
use SqlSemantics\Model\Validation\InvalidStructure;

#[CoversClass(FactorRemoval::class)]
#[Medium]
final class FactorRemovalTest extends TestCase
{
    public function testKeepsTheRequestOrderOfDistinctFactors(): void
    {
        $removal = new FactorRemoval(CurrentAccount::Authenticated, [AuthenticationFactor::Third, AuthenticationFactor::Second]);
        self::assertSame([AuthenticationFactor::Third, AuthenticationFactor::Second], $removal->factors);
    }


    public function testRejectsARepeatedFactor(): void
    {
        $this->expectException(InvalidStructure::class);
        new FactorRemoval(CurrentAccount::Authenticated, [AuthenticationFactor::Third, AuthenticationFactor::Third]);
    }
}
