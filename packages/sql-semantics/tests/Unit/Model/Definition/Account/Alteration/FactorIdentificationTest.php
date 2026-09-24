<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\Account\Alteration;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Model\Definition\Account\Alteration\AuthenticationFactor;
use SqlSemantics\Model\Definition\Account\Alteration\FactorIdentification;
use SqlSemantics\Model\Definition\Account\Identification\RandomPassword;

#[CoversClass(FactorIdentification::class)]
#[Medium]
final class FactorIdentificationTest extends TestCase
{
    public function testPairsTheFactorWithItsIdentification(): void
    {
        $factor = new FactorIdentification(AuthenticationFactor::Third, RandomPassword::Generated);
        self::assertSame(AuthenticationFactor::Third, $factor->factor);
        self::assertSame(RandomPassword::Generated, $factor->identification);
    }
}
