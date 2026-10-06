<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Account\ResourceGroup;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Account\ResourceGroup\DropResourceGroup;

#[CoversClass(DropResourceGroup::class)]
#[Medium]
final class DropResourceGroupTest extends TestCase
{
    public function testDeriveStatementRecordsNoFact(): void
    {
        self::assertSame([], (new Semantics(Dialect::MySql))->analyze('DROP RESOURCE GROUP g')->facts->diagnostics);
    }

    public function testRenderWritesForce(): void
    {
        self::assertSame('DROP RESOURCE GROUP g FORCE', (new Semantics(Dialect::MySql))->analyze('drop resource group g force')->toString());
    }
}
