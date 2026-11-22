<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Transaction;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Statement\Transaction\Commit;
use SqlSemantics\Testing\Sqlite\Inspection;

#[CoversClass(Commit::class)]
#[Medium]
final class CommitTest extends TestCase
{
    public function testDeriveStatementRecordsNoFact(): void
    {
        $operation = (new Inspection())->inspect('COMMIT', []);

        self::assertNull($operation->shape());
        self::assertSame([], $operation->facts->diagnostics);
    }

    public function testRenderWritesEndAsCommit(): void
    {
        $semantics = new Inspection();

        self::assertSame('COMMIT', $semantics->inspect('END')->toString());
        self::assertSame('COMMIT', $semantics->inspect('commit transaction')->toString());
        self::assertInstanceOf(Commit::class, $semantics->inspect('END TRANSACTION')->statement);
    }

    public function testRenderKeepsTheIgnoredName(): void
    {
        $operation = (new Inspection())->inspect('END TRANSACTION t1');

        self::assertInstanceOf(Commit::class, $operation->statement);
        self::assertSame('t1', $operation->statement->name?->value);
        self::assertSame('COMMIT TRANSACTION t1', $operation->toString());
    }
}
