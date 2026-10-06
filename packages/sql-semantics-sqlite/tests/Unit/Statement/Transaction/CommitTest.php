<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Transaction;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;
use SqlSemantics\Platform\Sqlite\Statement\Transaction\Commit;

#[CoversClass(Commit::class)]
#[Medium]
final class CommitTest extends TestCase
{
    public function testDeriveStatementRecordsNoFact(): void
    {
        $operation = (new Semantics(Dialect::Sqlite))->analyze('COMMIT', []);

        self::assertNull($operation->shape());
        self::assertSame([], $operation->facts->diagnostics);
    }

    public function testRenderWritesEndAsCommit(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);

        self::assertSame('COMMIT', $semantics->analyze('END')->toString());
        self::assertSame('COMMIT', $semantics->analyze('commit transaction')->toString());
        self::assertInstanceOf(Commit::class, $semantics->analyze('END TRANSACTION')->statement);
    }

    public function testRenderKeepsTheIgnoredName(): void
    {
        $operation = (new Semantics(Dialect::Sqlite))->analyze('END TRANSACTION t1');

        self::assertInstanceOf(Commit::class, $operation->statement);
        self::assertSame('t1', $operation->statement->name?->value);
        self::assertSame('COMMIT TRANSACTION t1', $operation->toString());
    }
}
