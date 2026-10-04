<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Catalog\Database;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Catalog\Database\RefreshDatabaseCollation::class)]
#[Medium]
final class RefreshDatabaseCollationTest extends TestCase
{
    public function testRenderWritesTheRequest(): void
    {
        self::assertSame('ALTER DATABASE d REFRESH COLLATION VERSION', (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('ALTER DATABASE d REFRESH COLLATION VERSION')->toString());
    }

    public function testDeriveStatementRecordsNothing(): void
    {
        self::assertSame([], (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('ALTER DATABASE d REFRESH COLLATION VERSION')->facts->diagnostics);
    }
}
