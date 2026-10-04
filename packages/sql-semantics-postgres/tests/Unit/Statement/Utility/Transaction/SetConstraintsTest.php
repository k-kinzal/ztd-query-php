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
        self::assertSame([], (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('SET CONSTRAINTS a IMMEDIATE')->facts->diagnostics);
    }

    public function testRenderWritesAllOrTheNames(): void
    {
        self::assertSame(['SET CONSTRAINTS ALL DEFERRED', 'SET CONSTRAINTS s.a, b IMMEDIATE'], [(new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('SET CONSTRAINTS ALL DEFERRED')->toString(), (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('SET CONSTRAINTS s.a, b IMMEDIATE')->toString()]);
    }

    public function testNamesMustBeQualifiedNames(): void
    {
        $this->expectException(\SqlSemantics\Diagnostic\InvalidConstruction::class);
        $this->expectExceptionMessage('SET CONSTRAINTS takes constraint names.');
        new \SqlSemantics\Platform\PostgreSql\Statement\Utility\Transaction\SetConstraints([new \SqlSemantics\Statement\Identifier\Name('a')], true);
    }
}
