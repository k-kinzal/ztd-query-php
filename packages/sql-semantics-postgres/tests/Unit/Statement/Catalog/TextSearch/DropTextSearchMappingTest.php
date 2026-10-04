<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Catalog\TextSearch;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Catalog\TextSearch\DropTextSearchMapping::class)]
#[Medium]
final class DropTextSearchMappingTest extends TestCase
{
    public function testRenderWritesIfExists(): void
    {
        self::assertSame('ALTER TEXT SEARCH CONFIGURATION c DROP MAPPING IF EXISTS FOR word, url', (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('ALTER TEXT SEARCH CONFIGURATION c DROP MAPPING IF EXISTS FOR word, url')->toString());
    }

    public function testDeriveStatementRecordsNothing(): void
    {
        self::assertSame([], (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('ALTER TEXT SEARCH CONFIGURATION c DROP MAPPING FOR word')->facts->diagnostics);
    }
}
