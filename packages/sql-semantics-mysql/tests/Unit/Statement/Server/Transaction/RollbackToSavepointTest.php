<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Server\Transaction;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Server\Transaction\RollbackToSavepoint;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(RollbackToSavepoint::class)]
#[Medium]
final class RollbackToSavepointTest extends TestCase
{
    public function testRenderWritesTheSavepoint(): void
    {
        self::assertSame('ROLLBACK TO SAVEPOINT `s 1`', (new Inspection())->inspect('rollback to `s 1`')->toString());
    }

    public function testDeriveStatementHasNoFacts(): void
    {
        $operation = (new Inspection())->inspect('ROLLBACK WORK TO SAVEPOINT s');

        self::assertSame([], $operation->facts->diagnostics);
        self::assertNull($operation->facts->output);
    }
}
