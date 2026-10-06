<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Catalog\Cast;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Catalog\Cast\CreateTransform::class)]
#[Medium]
final class CreateTransformTest extends TestCase
{
    public function testRenderKeepsTheOrder(): void
    {
        self::assertSame('CREATE OR REPLACE TRANSFORM FOR t LANGUAGE l (TO SQL WITH FUNCTION b (internal), FROM SQL WITH FUNCTION a (internal))', (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('CREATE OR REPLACE TRANSFORM FOR t LANGUAGE l (TO SQL WITH FUNCTION b(internal), FROM SQL WITH FUNCTION a(internal))')->toString());
    }

    public function testDeriveStatementDerivesTheFunctions(): void
    {
        self::assertSame([], (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('CREATE TRANSFORM FOR t LANGUAGE l (FROM SQL WITH FUNCTION a(internal))')->facts->diagnostics);
    }
}
