<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Server\Flush;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Server\Flush\Flush;

#[CoversClass(Flush::class)]
#[Medium]
final class FlushTest extends TestCase
{
    public function testRenderWritesTheItems(): void
    {
        self::assertSame('FLUSH NO_WRITE_TO_BINLOG PRIVILEGES, RELAY LOGS', (new Semantics(Dialect::MySql))->analyze('flush no_write_to_binlog privileges, relay logs')->toString());
    }

    public function testDeriveStatementHasNoFacts(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('FLUSH LOGS');

        self::assertSame([], $operation->facts->diagnostics);
        self::assertNull($operation->facts->output);
    }
}
