<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Catalog\Tablespace;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Catalog\Tablespace\CreateTablespace::class)]
#[Medium]
final class CreateTablespaceTest extends TestCase
{
    public function testRenderWritesOwnerLocationAndParameters(): void
    {
        self::assertSame('CREATE TABLESPACE fast OWNER app LOCATION \'/ssd\' WITH (random_page_cost = 1)', (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('CREATE TABLESPACE fast OWNER app LOCATION \'/ssd\' WITH (random_page_cost = 1)')->toString());
    }

    public function testDeriveStatementDerivesTheParameters(): void
    {
        self::assertSame([], (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('CREATE TABLESPACE fast LOCATION \'/ssd\'')->facts->diagnostics);
    }
}
