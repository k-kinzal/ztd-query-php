<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Server\ForeignServer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Server\ForeignServer\DropServer;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(DropServer::class)]
#[Medium]
final class DropServerTest extends TestCase
{
    public function testRenderWritesTheName(): void
    {
        self::assertSame('DROP SERVER s', (new Inspection())->inspect('drop server s')->toString());
    }

    public function testDeriveStatementHasNoFacts(): void
    {
        $operation = (new Inspection())->inspect('DROP SERVER IF EXISTS s');

        self::assertSame([], $operation->facts->diagnostics);
        self::assertNull($operation->facts->output);
    }
}
