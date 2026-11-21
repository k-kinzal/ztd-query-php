<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Server\Transaction;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Semantics;
use SqlSemantics\Platform\MySql\Statement\Server\Transaction\RollbackToSavepoint;

#[CoversClass(RollbackToSavepoint::class)]
#[Medium]
final class RollbackToSavepointTest extends TestCase
{
    public function testRenderWritesTheSavepoint(): void
    {
        self::assertSame('ROLLBACK TO SAVEPOINT `s 1`', (new Semantics())->analyze('rollback to `s 1`')->toString());
    }

    public function testDeriveStatementHasNoFacts(): void
    {
        $operation = (new Semantics())->analyze('ROLLBACK WORK TO SAVEPOINT s');

        self::assertSame([], $operation->facts->diagnostics);
        self::assertNull($operation->facts->output);
    }
}
