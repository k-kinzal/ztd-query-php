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
        self::assertSame([], (new \SqlSemantics\Testing\PostgreSql\Inspection())->inspect('BEGIN ISOLATION LEVEL SERIALIZABLE')->facts->diagnostics);
    }

    public function testRenderDropsTheNoiseWordAndCommas(): void
    {
        self::assertSame('BEGIN ISOLATION LEVEL REPEATABLE READ READ WRITE NOT DEFERRABLE', (new \SqlSemantics\Testing\PostgreSql\Inspection())->inspect('BEGIN TRANSACTION ISOLATION LEVEL REPEATABLE READ, READ WRITE, NOT DEFERRABLE')->toString());
    }

    public function testRenderKeepsRepeatedModesInOrder(): void
    {
        self::assertSame('START TRANSACTION READ ONLY READ WRITE READ ONLY', (new \SqlSemantics\Testing\PostgreSql\Inspection())->inspect('START TRANSACTION READ ONLY READ WRITE READ ONLY')->toString());
    }
}
