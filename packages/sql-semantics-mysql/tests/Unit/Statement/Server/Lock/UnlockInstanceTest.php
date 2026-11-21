<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Server\Lock;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Semantics;
use SqlSemantics\Platform\MySql\Statement\Server\Lock\UnlockInstance;

#[CoversClass(UnlockInstance::class)]
#[Medium]
final class UnlockInstanceTest extends TestCase
{
    public function testRenderWritesTheRequest(): void
    {
        self::assertSame('UNLOCK INSTANCE', (new Semantics())->analyze('unlock instance')->toString());
    }

    public function testDeriveStatementHasNoFacts(): void
    {
        $operation = (new Semantics())->analyze('UNLOCK INSTANCE');

        self::assertSame([], $operation->facts->diagnostics);
        self::assertNull($operation->facts->output);
    }
}
