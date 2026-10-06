<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Account\ResourceGroup;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Account\ResourceGroup\CpuRange;

#[CoversClass(CpuRange::class)]
#[Medium]
final class CpuRangeTest extends TestCase
{
    public function testRenderWritesNumbersAndRanges(): void
    {
        self::assertSame('ALTER RESOURCE GROUP g VCPU = 0, 2 - 5', (new Semantics(Dialect::MySql))->analyze('alter resource group g vcpu 0 2-5')->toString());
    }
}
