<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Transaction\Configuration;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Model\Transaction\Configuration\Deferrability;

#[CoversClass(Deferrability::class)]
#[Medium]
final class DeferrabilityTest extends TestCase
{
    public function testFromSelectsAnExplicitTransactionPolicy(): void
    {
        self::assertSame(Deferrability::NotDeferrable, Deferrability::from('NOT DEFERRABLE'));
    }
}
