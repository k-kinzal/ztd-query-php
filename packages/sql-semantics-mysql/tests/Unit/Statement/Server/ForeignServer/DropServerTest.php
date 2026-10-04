<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Server\ForeignServer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Server\ForeignServer\DropServer;

#[CoversClass(DropServer::class)]
#[Medium]
final class DropServerTest extends TestCase
{
    public function testRenderWritesTheName(): void
    {
        self::assertSame('DROP SERVER s', (new Semantics(Dialect::MySql))->analyze('drop server s')->toString());
    }

    public function testDeriveStatementHasNoFacts(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('DROP SERVER IF EXISTS s');

        self::assertSame([], $operation->facts->diagnostics);
        self::assertNull($operation->facts->output);
    }
}
