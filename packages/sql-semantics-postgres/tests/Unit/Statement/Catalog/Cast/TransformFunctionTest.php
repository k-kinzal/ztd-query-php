<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Catalog\Cast;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Catalog\Cast\TransformFunction::class)]
#[Medium]
final class TransformFunctionTest extends TestCase
{
    public function testRenderWritesTheDirection(): void
    {
        self::assertSame('CREATE TRANSFORM FOR hstore LANGUAGE plpython3u (TO SQL WITH FUNCTION f (internal))', (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('CREATE TRANSFORM FOR hstore LANGUAGE plpython3u (TO SQL WITH FUNCTION f(internal))')->toString());
    }

    public function testDeriveClauseDerivesTheSignature(): void
    {
        self::assertSame([], (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('CREATE TRANSFORM FOR t LANGUAGE l (FROM SQL WITH FUNCTION f(numeric(4, 2)))')->facts->diagnostics);
    }
}
