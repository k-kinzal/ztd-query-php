<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Transaction;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Statement\Transaction\Rollback;
use SqlSemantics\Testing\Sqlite\Inspection;

#[CoversClass(Rollback::class)]
#[Medium]
final class RollbackTest extends TestCase
{
    public function testDeriveStatementRecordsNoFact(): void
    {
        $operation = (new Inspection())->inspect('ROLLBACK', []);

        self::assertNull($operation->shape());
        self::assertSame([], $operation->facts->diagnostics);
    }

    public function testRenderDropsTheOptionalKeywordAndKeepsTheIgnoredName(): void
    {
        $semantics = new Inspection();

        self::assertInstanceOf(Rollback::class, $semantics->inspect('ROLLBACK')->statement);
        self::assertSame('ROLLBACK', $semantics->inspect('rollback transaction')->toString());
        self::assertSame('ROLLBACK TRANSACTION t1', $semantics->inspect('rollback transaction t1')->toString());
    }
}
