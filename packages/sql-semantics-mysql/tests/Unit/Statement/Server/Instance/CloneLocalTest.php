<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Server\Instance;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Server\Instance\CloneLocal;

#[CoversClass(CloneLocal::class)]
#[Medium]
final class CloneLocalTest extends TestCase
{
    public function testValueRejectsRadixText(): void
    {
        $this->expectExceptionMessage('A clone directory is quoted text.');

        new CloneLocal(new \SqlSemantics\Platform\MySql\Statement\Literal\Text('10', \SqlSemantics\Platform\MySql\Statement\Literal\Radix::Bit));
    }

    public function testRenderWritesTheDirectory(): void
    {
        self::assertSame("CLONE LOCAL DATA DIRECTORY '/d'", (new Semantics(Dialect::MySql))->analyze("clone local data directory '/d'")->toString());
    }

    public function testDeriveStatementHasNoFacts(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze("CLONE LOCAL DATA DIRECTORY = '/d'");

        self::assertSame([], $operation->facts->diagnostics);
        self::assertNull($operation->facts->output);
    }
}
