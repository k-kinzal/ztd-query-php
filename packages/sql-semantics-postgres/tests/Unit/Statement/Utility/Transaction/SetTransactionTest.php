<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Utility\Transaction;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Utility\Transaction\SetTransaction::class)]
#[Medium]
final class SetTransactionTest extends TestCase
{
    public function testDeriveStatementRecordsNoProblem(): void
    {
        self::assertSame([], (new \SqlSemantics\Testing\PostgreSql\Inspection())->inspect('SET TRANSACTION ISOLATION LEVEL READ UNCOMMITTED')->facts->diagnostics);
    }

    public function testRenderDropsTheCommasAndKeepsLocal(): void
    {
        self::assertSame('SET LOCAL TRANSACTION ISOLATION LEVEL READ COMMITTED DEFERRABLE', (new \SqlSemantics\Testing\PostgreSql\Inspection())->inspect('SET LOCAL TRANSACTION ISOLATION LEVEL READ COMMITTED, DEFERRABLE')->toString());
    }
}
