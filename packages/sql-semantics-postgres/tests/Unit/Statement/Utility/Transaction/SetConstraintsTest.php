<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Utility\Transaction;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Utility\Transaction\SetConstraints::class)]
#[Medium]
final class SetConstraintsTest extends TestCase
{
    public function testDeriveStatementRecordsNoProblem(): void
    {
        self::assertSame([], (new \Tests\Support\Inspection())->inspect('SET CONSTRAINTS a IMMEDIATE')->facts->diagnostics);
    }

    public function testRenderWritesAllOrTheNames(): void
    {
        self::assertSame(['SET CONSTRAINTS ALL DEFERRED', 'SET CONSTRAINTS s.a, b IMMEDIATE'], [(new \Tests\Support\Inspection())->inspect('SET CONSTRAINTS ALL DEFERRED')->toString(), (new \Tests\Support\Inspection())->inspect('SET CONSTRAINTS s.a, b IMMEDIATE')->toString()]);
    }
}
