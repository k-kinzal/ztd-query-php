<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Catalog\TextSearch;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Catalog\TextSearch\AlterTextSearchDictionary::class)]
#[Medium]
final class AlterTextSearchDictionaryTest extends TestCase
{
    public function testRenderWritesTheOptions(): void
    {
        self::assertSame('ALTER TEXT SEARCH DICTIONARY my_dict (stopwords = newrussian)', (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('ALTER TEXT SEARCH DICTIONARY my_dict (StopWords = newrussian)')->toString());
    }

    public function testDeriveStatementDerivesTheOptions(): void
    {
        self::assertSame([], (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('ALTER TEXT SEARCH DICTIONARY d (dummy)')->facts->diagnostics);
    }
}
