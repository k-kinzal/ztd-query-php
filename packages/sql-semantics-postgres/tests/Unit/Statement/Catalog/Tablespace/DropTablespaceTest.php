<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Catalog\Tablespace;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Catalog\Tablespace\DropTablespace::class)]
#[Medium]
final class DropTablespaceTest extends TestCase
{
    public function testRenderWritesIfExists(): void
    {
        self::assertSame('DROP TABLESPACE IF EXISTS fast', (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('DROP TABLESPACE IF EXISTS fast')->toString());
    }

    public function testDeriveStatementRecordsNothing(): void
    {
        self::assertSame([], (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('DROP TABLESPACE fast')->facts->diagnostics);
    }
}
