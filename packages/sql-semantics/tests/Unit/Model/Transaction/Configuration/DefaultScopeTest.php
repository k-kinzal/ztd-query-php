<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Transaction\Configuration;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Model\Transaction\Configuration\DefaultScope;

#[CoversClass(DefaultScope::class)]
#[Medium]
final class DefaultScopeTest extends TestCase
{
    public function testFromSelectsAnExplicitTransactionPolicy(): void
    {
        self::assertSame(DefaultScope::Session, DefaultScope::from('SESSION'));
    }
}
