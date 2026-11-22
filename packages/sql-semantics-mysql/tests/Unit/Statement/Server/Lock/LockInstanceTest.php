<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Server\Lock;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Server\Lock\LockInstance;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(LockInstance::class)]
#[Medium]
final class LockInstanceTest extends TestCase
{
    public function testRenderWritesTheRequest(): void
    {
        self::assertSame('LOCK INSTANCE FOR BACKUP', (new Inspection())->inspect('lock instance for backup')->toString());
    }

    public function testDeriveStatementHasNoFacts(): void
    {
        $operation = (new Inspection())->inspect('LOCK INSTANCE FOR BACKUP');

        self::assertSame([], $operation->facts->diagnostics);
        self::assertNull($operation->facts->output);
    }
}
