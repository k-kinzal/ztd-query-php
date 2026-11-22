<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Server\Lock;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Server\Lock\UnlockTables;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(UnlockTables::class)]
#[Medium]
final class UnlockTablesTest extends TestCase
{
    public function testRenderWritesTables(): void
    {
        self::assertSame('UNLOCK TABLES', (new Inspection())->inspect('unlock table')->toString());
    }

    public function testDeriveStatementHasNoFacts(): void
    {
        $operation = (new Inspection())->inspect('UNLOCK TABLES');

        self::assertSame([], $operation->facts->diagnostics);
        self::assertNull($operation->facts->output);
    }
}
