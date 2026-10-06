<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Catalog\Database;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Catalog\Database\CreateDatabase::class)]
#[Medium]
final class CreateDatabaseTest extends TestCase
{
    public function testRenderWritesTheOptions(): void
    {
        self::assertSame('CREATE DATABASE d WITH OWNER = app ENCODING = \'UTF8\'', (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('CREATE DATABASE d WITH OWNER = app ENCODING \'UTF8\'')->toString());
    }

    public function testRenderWithoutOptions(): void
    {
        self::assertSame('CREATE DATABASE d', (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('CREATE DATABASE d')->toString());
    }

    public function testDeriveStatementReportsAnUnknownOption(): void
    {
        self::assertSame('option "foo" not recognized', (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('CREATE DATABASE d foo = 1')->facts->diagnostics[0]->message());
    }

    public function testDeriveStatementAcceptsTheBuiltinLocaleOf17(): void
    {
        self::assertSame([], (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('CREATE DATABASE d builtin_locale = \'C.UTF-8\'')->facts->diagnostics);
    }

    public function testDeriveStatementRejectsTheBuiltinLocaleOf16(): void
    {
        self::assertSame('option "builtin_locale" not recognized', (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql, 'pg-16.6'))->analyze('CREATE DATABASE d builtin_locale = \'C.UTF-8\'')->facts->diagnostics[0]->message());
    }
}
