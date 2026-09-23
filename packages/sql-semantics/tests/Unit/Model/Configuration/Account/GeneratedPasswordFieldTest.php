<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Configuration\Account;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Model\Configuration\Account\GeneratedPasswordField;

#[CoversClass(GeneratedPasswordField::class)]
#[Medium]
final class GeneratedPasswordFieldTest extends TestCase
{
    public function testFromSelectsAnExplicitAccountOperation(): void
    {
        self::assertSame(GeneratedPasswordField::Password, GeneratedPasswordField::from('generated password'));
    }
}
