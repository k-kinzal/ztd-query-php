<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Account\ResourceGroup;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Account\Problem\NumberOutOfRange;
use SqlSemantics\Platform\MySql\Statement\Account\ResourceGroup\SetResourceGroup;

#[CoversClass(SetResourceGroup::class)]
#[Medium]
final class SetResourceGroupTest extends TestCase
{
    public function testDeriveStatementReportsANonIntegerThread(): void
    {
        self::assertInstanceOf(NumberOutOfRange::class, (new Semantics(Dialect::MySql))->analyze('SET RESOURCE GROUP g FOR 1.5')->facts->diagnostics[0]);
    }

    public function testRenderWritesTheThreads(): void
    {
        self::assertSame('SET RESOURCE GROUP g FOR 1, 2', (new Semantics(Dialect::MySql))->analyze('set resource group g for 1, 2')->toString());
    }
}
