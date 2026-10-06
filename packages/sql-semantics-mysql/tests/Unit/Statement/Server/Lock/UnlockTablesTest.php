<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Server\Lock;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Server\Lock\UnlockTables;

#[CoversClass(UnlockTables::class)]
#[Medium]
final class UnlockTablesTest extends TestCase
{
    public function testRenderWritesTables(): void
    {
        self::assertSame('UNLOCK TABLES', (new Semantics(Dialect::MySql))->analyze('unlock table')->toString());
    }

    public function testDeriveStatementHasNoFacts(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('UNLOCK TABLES');

        self::assertSame([], $operation->facts->diagnostics);
        self::assertNull($operation->facts->output);
    }
}
