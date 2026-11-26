<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Server\Instance;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Server\Instance\Restart;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(Restart::class)]
#[Medium]
final class RestartTest extends TestCase
{
    public function testRenderWritesTheRequest(): void
    {
        self::assertSame('RESTART', (new Inspection())->inspect('restart')->toString());
    }

    public function testDeriveStatementHasNoFacts(): void
    {
        $operation = (new Inspection())->inspect('RESTART');

        self::assertSame([], $operation->facts->diagnostics);
        self::assertNull($operation->facts->output);
    }
}
