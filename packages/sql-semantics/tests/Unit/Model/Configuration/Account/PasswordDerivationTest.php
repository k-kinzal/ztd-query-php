<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Configuration\Account;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Model\Configuration\Account\PasswordDerivation;

#[CoversClass(PasswordDerivation::class)]
#[Medium]
final class PasswordDerivationTest extends TestCase
{
    public function testFromSelectsAnExplicitAccountOperation(): void
    {
        self::assertSame(PasswordDerivation::Pre41, PasswordDerivation::from('OLD_PASSWORD'));
    }
}
