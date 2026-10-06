<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Server\Instance;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Server\Instance\Restart;

#[CoversClass(Restart::class)]
#[Medium]
final class RestartTest extends TestCase
{
    public function testRenderWritesTheRequest(): void
    {
        self::assertSame('RESTART', (new Semantics(Dialect::MySql))->analyze('restart')->toString());
    }

    public function testDeriveStatementHasNoFacts(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('RESTART');

        self::assertSame([], $operation->facts->diagnostics);
        self::assertNull($operation->facts->output);
    }
}
