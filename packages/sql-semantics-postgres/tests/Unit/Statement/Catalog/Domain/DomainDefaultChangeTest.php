<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Catalog\Domain;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Catalog\Domain\DomainDefaultChange::class)]
#[Medium]
final class DomainDefaultChangeTest extends TestCase
{
    public function testRenderWritesSetDefault(): void
    {
        self::assertSame('ALTER DOMAIN d SET DEFAULT 0', (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('ALTER DOMAIN d SET DEFAULT 0')->toString());
    }

    public function testRenderWritesDropDefault(): void
    {
        self::assertSame('ALTER DOMAIN d DROP DEFAULT', (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('ALTER DOMAIN d DROP DEFAULT')->toString());
    }

    public function testDeriveStatementDerivesTheDefault(): void
    {
        self::assertSame([], (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('ALTER DOMAIN d SET DEFAULT 1 + 1')->facts->diagnostics);
    }
}
