<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Catalog\Cast;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Catalog\Cast\CreateCast::class)]
#[Medium]
final class CreateCastTest extends TestCase
{
    public function testRenderWritesTheFunction(): void
    {
        self::assertSame('CREATE CAST (a AS b) WITH FUNCTION f (int4) AS IMPLICIT', (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('CREATE CAST (a AS b) WITH FUNCTION f(int4) AS IMPLICIT')->toString());
    }

    public function testDeriveStatementDerivesTheTypes(): void
    {
        self::assertSame([], (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('CREATE CAST (numeric(4, 2) AS b) WITHOUT FUNCTION')->facts->diagnostics);
    }
}
