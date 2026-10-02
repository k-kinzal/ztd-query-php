<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Transaction;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;
use SqlSemantics\Platform\Sqlite\Statement\Transaction\RollbackTo;

#[CoversClass(RollbackTo::class)]
#[Medium]
final class RollbackToTest extends TestCase
{
    public function testDeriveStatementRecordsNoFact(): void
    {
        $operation = (new Semantics(Dialect::Sqlite))->analyze('ROLLBACK TO s1', []);

        self::assertNull($operation->shape());
        self::assertSame([], $operation->facts->diagnostics);
    }

    public function testRenderKeepsTheSavepointAndTheIgnoredTransactionName(): void
    {
        $operation = (new Semantics(Dialect::Sqlite))->analyze('rollback transaction t1 to savepoint s1');

        self::assertInstanceOf(RollbackTo::class, $operation->statement);
        self::assertSame('s1', $operation->statement->savepoint->value);
        self::assertSame('t1', $operation->statement->name?->value);
        self::assertSame('ROLLBACK TRANSACTION t1 TO s1', $operation->toString());
    }

    public function testRenderDropsTheOptionalKeywords(): void
    {
        self::assertSame('ROLLBACK TO s1', (new Semantics(Dialect::Sqlite))->analyze('ROLLBACK TRANSACTION TO SAVEPOINT s1')->toString());
    }
}
