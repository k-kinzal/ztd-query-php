<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Catalog\Collation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Catalog\Collation\RefreshCollationVersion::class)]
#[Medium]
final class RefreshCollationVersionTest extends TestCase
{
    public function testRenderQuotesTheName(): void
    {
        self::assertSame('ALTER COLLATION "de_DE" REFRESH VERSION', (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('ALTER COLLATION "de_DE" REFRESH VERSION')->toString());
    }

    public function testDeriveStatementRecordsNothing(): void
    {
        self::assertSame([], (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('ALTER COLLATION c REFRESH VERSION')->facts->diagnostics);
    }
}
