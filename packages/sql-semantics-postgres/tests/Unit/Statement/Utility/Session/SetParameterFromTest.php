<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Utility\Session;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Utility\Session\SetParameterFrom::class)]
#[Medium]
final class SetParameterFromTest extends TestCase
{
    public function testDeriveStatementRecordsNoProblem(): void
    {
        self::assertSame([], (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('SET a FROM CURRENT')->facts->diagnostics);
    }

    public function testRenderWritesToDefault(): void
    {
        self::assertSame('SET LOCAL a TO DEFAULT', (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('SET LOCAL a = DEFAULT')->toString());
    }

    public function testRenderWritesFromCurrent(): void
    {
        self::assertSame('SET a FROM CURRENT', (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('SET a FROM CURRENT')->toString());
    }
}
