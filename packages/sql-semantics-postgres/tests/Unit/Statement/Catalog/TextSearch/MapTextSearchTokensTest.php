<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Catalog\TextSearch;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Catalog\TextSearch\MapTextSearchTokens::class)]
#[Medium]
final class MapTextSearchTokensTest extends TestCase
{
    public function testRenderWritesTokensAndDictionaries(): void
    {
        self::assertSame('ALTER TEXT SEARCH CONFIGURATION c ADD MAPPING FOR word, asciiword WITH simple, s.english', (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('ALTER TEXT SEARCH CONFIGURATION c ADD MAPPING FOR word, asciiword WITH simple, s.english')->toString());
    }

    public function testDeriveStatementRecordsNothing(): void
    {
        self::assertSame([], (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('ALTER TEXT SEARCH CONFIGURATION c ADD MAPPING FOR word WITH simple')->facts->diagnostics);
    }
}
