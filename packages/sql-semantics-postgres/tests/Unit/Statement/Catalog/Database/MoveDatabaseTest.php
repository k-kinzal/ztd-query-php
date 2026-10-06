<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Catalog\Database;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Catalog\Database\MoveDatabase::class)]
#[Medium]
final class MoveDatabaseTest extends TestCase
{
    public function testRenderWritesSetTablespace(): void
    {
        self::assertSame('ALTER DATABASE d SET TABLESPACE fast', (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('ALTER DATABASE d SET TABLESPACE fast')->toString());
    }

    public function testDeriveStatementRecordsNothing(): void
    {
        self::assertSame([], (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('ALTER DATABASE d SET TABLESPACE fast')->facts->diagnostics);
    }
}
