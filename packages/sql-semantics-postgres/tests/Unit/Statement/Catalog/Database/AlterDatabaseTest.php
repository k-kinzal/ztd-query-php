<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Catalog\Database;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Catalog\Database\AlterDatabase::class)]
#[Medium]
final class AlterDatabaseTest extends TestCase
{
    public function testRenderWritesWith(): void
    {
        self::assertSame('ALTER DATABASE d WITH CONNECTION LIMIT = 10', (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('ALTER DATABASE d CONNECTION LIMIT 10')->toString());
    }

    public function testDeriveStatementReportsATablespaceWithOtherOptions(): void
    {
        self::assertSame('option "tablespace" cannot be specified with other options', (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('ALTER DATABASE d WITH tablespace x allow_connections false')->facts->diagnostics[0]->message());
    }

    public function testDeriveStatementReportsAnOptionOnlyCreateAccepts(): void
    {
        self::assertSame('option "owner" not recognized', (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('ALTER DATABASE d OWNER x')->facts->diagnostics[0]->message());
    }
}
