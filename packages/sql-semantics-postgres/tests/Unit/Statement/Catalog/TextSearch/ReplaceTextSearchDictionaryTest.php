<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Catalog\TextSearch;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Catalog\TextSearch\ReplaceTextSearchDictionary::class)]
#[Medium]
final class ReplaceTextSearchDictionaryTest extends TestCase
{
    public function testRenderWithoutTokens(): void
    {
        self::assertSame('ALTER TEXT SEARCH CONFIGURATION c ALTER MAPPING REPLACE english WITH swedish', (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('ALTER TEXT SEARCH CONFIGURATION c ALTER MAPPING REPLACE english WITH swedish')->toString());
    }

    public function testRenderWithTokens(): void
    {
        self::assertSame('ALTER TEXT SEARCH CONFIGURATION c ALTER MAPPING FOR a, b REPLACE english WITH swedish', (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('ALTER TEXT SEARCH CONFIGURATION c ALTER MAPPING FOR a, b REPLACE english WITH swedish')->toString());
    }

    public function testDeriveStatementRecordsNothing(): void
    {
        self::assertSame([], (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('ALTER TEXT SEARCH CONFIGURATION c ALTER MAPPING REPLACE a WITH b')->facts->diagnostics);
    }
}
