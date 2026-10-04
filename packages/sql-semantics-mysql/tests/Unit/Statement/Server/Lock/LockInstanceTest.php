<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Server\Lock;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Server\Lock\LockInstance;

#[CoversClass(LockInstance::class)]
#[Medium]
final class LockInstanceTest extends TestCase
{
    public function testRenderWritesTheRequest(): void
    {
        self::assertSame('LOCK INSTANCE FOR BACKUP', (new Semantics(Dialect::MySql))->analyze('lock instance for backup')->toString());
    }

    public function testDeriveStatementHasNoFacts(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('LOCK INSTANCE FOR BACKUP');

        self::assertSame([], $operation->facts->diagnostics);
        self::assertNull($operation->facts->output);
    }
}
