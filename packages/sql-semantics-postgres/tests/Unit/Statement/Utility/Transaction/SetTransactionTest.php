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
        self::assertSame([], (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('SET TRANSACTION ISOLATION LEVEL READ UNCOMMITTED')->facts->diagnostics);
    }

    public function testRenderDropsTheCommasAndKeepsLocal(): void
    {
        self::assertSame('SET LOCAL TRANSACTION ISOLATION LEVEL READ COMMITTED DEFERRABLE', (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('SET LOCAL TRANSACTION ISOLATION LEVEL READ COMMITTED, DEFERRABLE')->toString());
    }

    public function testAModeIsRequired(): void
    {
        $this->expectException(\SqlSemantics\Diagnostic\InvalidConstruction::class);
        $this->expectExceptionMessage('SET TRANSACTION takes at least one mode.');
        new \SqlSemantics\Platform\PostgreSql\Statement\Utility\Transaction\SetTransaction(\SqlSemantics\Platform\PostgreSql\Statement\Utility\Transaction\TransactionScope::Transaction, []);
    }
}
