<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Account\ResourceGroup;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Account\Problem\PriorityOutOfRange;
use SqlSemantics\Platform\MySql\Statement\Account\ResourceGroup\CreateResourceGroup;

#[CoversClass(CreateResourceGroup::class)]
#[Medium]
final class CreateResourceGroupTest extends TestCase
{
    public function testDeriveStatementReportsAPriorityOutsideTheRange(): void
    {
        $semantics = new Semantics(Dialect::MySql);

        self::assertInstanceOf(PriorityOutOfRange::class, $semantics->analyze('CREATE RESOURCE GROUP g TYPE = SYSTEM THREAD_PRIORITY 5')->facts->diagnostics[0]);
        self::assertInstanceOf(PriorityOutOfRange::class, $semantics->analyze('CREATE RESOURCE GROUP g TYPE = USER THREAD_PRIORITY 20')->facts->diagnostics[0]);
        self::assertSame([], $semantics->analyze('CREATE RESOURCE GROUP g TYPE = SYSTEM THREAD_PRIORITY -20')->facts->diagnostics);
    }

    public function testRenderWritesEveryOption(): void
    {
        self::assertSame('CREATE RESOURCE GROUP g TYPE = SYSTEM VCPU = 1 THREAD_PRIORITY = 0 ENABLE', (new Semantics(Dialect::MySql))->analyze('create resource group g type system vcpu = 1 thread_priority 0 enable')->toString());
    }
}
