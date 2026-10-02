<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Transaction;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;
use SqlSemantics\Platform\Sqlite\Statement\Transaction\Rollback;

#[CoversClass(Rollback::class)]
#[Medium]
final class RollbackTest extends TestCase
{
    public function testDeriveStatementRecordsNoFact(): void
    {
        $operation = (new Semantics(Dialect::Sqlite))->analyze('ROLLBACK', []);

        self::assertNull($operation->shape());
        self::assertSame([], $operation->facts->diagnostics);
    }

    public function testRenderDropsTheOptionalKeywordAndKeepsTheIgnoredName(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);

        self::assertInstanceOf(Rollback::class, $semantics->analyze('ROLLBACK')->statement);
        self::assertSame('ROLLBACK', $semantics->analyze('rollback transaction')->toString());
        self::assertSame('ROLLBACK TRANSACTION t1', $semantics->analyze('rollback transaction t1')->toString());
    }
}
