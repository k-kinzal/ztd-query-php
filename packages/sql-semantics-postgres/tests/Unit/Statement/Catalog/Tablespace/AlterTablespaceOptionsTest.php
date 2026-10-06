<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Catalog\Tablespace;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Catalog\Tablespace\AlterTablespaceOptions::class)]
#[Medium]
final class AlterTablespaceOptionsTest extends TestCase
{
    public function testRenderWritesReset(): void
    {
        self::assertSame('ALTER TABLESPACE fast RESET (seq_page_cost)', (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('ALTER TABLESPACE fast RESET (seq_page_cost)')->toString());
    }

    public function testDeriveStatementReportsAValueGivenToReset(): void
    {
        self::assertSame('RESET must not include values for parameters', (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('ALTER TABLESPACE fast RESET (seq_page_cost = 1)')->facts->diagnostics[0]->message());
    }

    public function testDeriveStatementAcceptsSetValues(): void
    {
        self::assertSame([], (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('ALTER TABLESPACE fast SET (seq_page_cost = 1)')->facts->diagnostics);
    }
}
