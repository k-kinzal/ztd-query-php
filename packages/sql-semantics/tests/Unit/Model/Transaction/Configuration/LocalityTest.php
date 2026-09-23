<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Transaction\Configuration;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Model\Transaction\Configuration\Locality;

#[CoversClass(Locality::class)]
#[Medium]
final class LocalityTest extends TestCase
{
    public function testFromSelectsAnExplicitTransactionPolicy(): void
    {
        self::assertSame(Locality::Local, Locality::from('LOCAL'));
    }
}
