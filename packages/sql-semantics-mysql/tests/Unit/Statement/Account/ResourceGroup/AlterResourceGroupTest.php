<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Account\ResourceGroup;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Semantics;
use SqlSemantics\Platform\MySql\Statement\Account\ResourceGroup\AlterResourceGroup;

#[CoversClass(AlterResourceGroup::class)]
#[Medium]
final class AlterResourceGroupTest extends TestCase
{
    public function testDeriveStatementRecordsNoFact(): void
    {
        self::assertSame([], (new Semantics())->analyze('ALTER RESOURCE GROUP g ENABLE')->facts->diagnostics);
    }

    public function testRenderWritesEveryOption(): void
    {
        self::assertSame('ALTER RESOURCE GROUP g VCPU = 3 THREAD_PRIORITY = 1 ENABLE FORCE', (new Semantics())->analyze('alter resource group g vcpu 3 thread_priority 1 enable force')->toString());
    }
}
