<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Catalog\Type;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Catalog\Type\AlterTypeOptions::class)]
#[Medium]
final class AlterTypeOptionsTest extends TestCase
{
    public function testRenderWritesNone(): void
    {
        self::assertSame('ALTER TYPE t SET (storage = plain, send = NONE)', (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('ALTER TYPE t SET (storage = plain, send = NONE)')->toString());
    }

    public function testDeriveStatementDerivesTheValues(): void
    {
        self::assertSame([], (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('ALTER TYPE t SET (receive = r)')->facts->diagnostics);
    }
}
