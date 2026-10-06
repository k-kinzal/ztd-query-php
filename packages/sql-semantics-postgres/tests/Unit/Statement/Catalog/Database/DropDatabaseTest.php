<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Catalog\Database;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Catalog\Database\DropDatabase::class)]
#[Medium]
final class DropDatabaseTest extends TestCase
{
    public function testRenderWritesWithBeforeTheOptions(): void
    {
        self::assertSame('DROP DATABASE IF EXISTS d WITH (FORCE)', (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('DROP DATABASE IF EXISTS d (FORCE)')->toString());
    }

    public function testDeriveStatementRecordsNothing(): void
    {
        self::assertSame([], (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('DROP DATABASE d')->facts->diagnostics);
    }
}
