<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Server\Instance;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Server\Instance\Shutdown;

#[CoversClass(Shutdown::class)]
#[Medium]
final class ShutdownTest extends TestCase
{
    public function testRenderWritesTheRequest(): void
    {
        self::assertSame('SHUTDOWN', (new Semantics(Dialect::MySql, 'mysql-5.7.44'))->analyze('shutdown')->toString());
    }

    public function testDeriveStatementHasNoFacts(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('SHUTDOWN');

        self::assertSame([], $operation->facts->diagnostics);
        self::assertNull($operation->facts->output);
    }
}
