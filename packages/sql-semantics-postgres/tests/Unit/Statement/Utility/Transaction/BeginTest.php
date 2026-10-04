<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Utility\Transaction;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Utility\Transaction\Begin::class)]
#[Medium]
final class BeginTest extends TestCase
{
    public function testDeriveStatementRecordsNoProblem(): void
    {
        self::assertSame([], (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('BEGIN ISOLATION LEVEL SERIALIZABLE')->facts->diagnostics);
    }

    public function testRenderDropsTheNoiseWordAndCommas(): void
    {
        self::assertSame('BEGIN ISOLATION LEVEL REPEATABLE READ READ WRITE NOT DEFERRABLE', (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('BEGIN TRANSACTION ISOLATION LEVEL REPEATABLE READ, READ WRITE, NOT DEFERRABLE')->toString());
    }

    public function testRenderKeepsRepeatedModesInOrder(): void
    {
        self::assertSame('START TRANSACTION READ ONLY READ WRITE READ ONLY', (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('START TRANSACTION READ ONLY READ WRITE READ ONLY')->toString());
    }

    public function testModesMustBeTransactionModes(): void
    {
        $this->expectException(\SqlSemantics\Diagnostic\InvalidConstruction::class);
        $this->expectExceptionMessage('The modes of a transaction are transaction modes.');
        new \SqlSemantics\Platform\PostgreSql\Statement\Utility\Transaction\Begin(\SqlSemantics\Platform\PostgreSql\Statement\Utility\Transaction\BeginSpelling::Begin, ['READ ONLY']);
    }
}
