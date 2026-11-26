<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Server\Instance;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Server\Instance\CloneLocal;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(CloneLocal::class)]
#[Medium]
final class CloneLocalTest extends TestCase
{
    public function testRenderWritesTheDirectory(): void
    {
        self::assertSame("CLONE LOCAL DATA DIRECTORY '/d'", (new Inspection())->inspect("clone local data directory '/d'")->toString());
    }

    public function testDeriveStatementHasNoFacts(): void
    {
        $operation = (new Inspection())->inspect("CLONE LOCAL DATA DIRECTORY = '/d'");

        self::assertSame([], $operation->facts->diagnostics);
        self::assertNull($operation->facts->output);
    }
}
