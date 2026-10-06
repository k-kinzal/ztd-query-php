<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Server\Transaction;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Server\Transaction\Rollback;

#[CoversClass(Rollback::class)]
#[Medium]
final class RollbackTest extends TestCase
{
    public function testRenderWritesTheCompletionChoices(): void
    {
        self::assertSame('ROLLBACK AND NO CHAIN RELEASE', (new Semantics(Dialect::MySql))->analyze('rollback work and no chain release')->toString());
    }

    public function testDeriveStatementHasNoFacts(): void
    {
        $operation = (new Semantics(Dialect::MySql, 'mysql-9.1.0'))->analyze('ROLLBACK');

        self::assertSame([], $operation->facts->diagnostics);
        self::assertNull($operation->facts->output);
    }
}
