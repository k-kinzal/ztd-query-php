<?php

declare(strict_types=1);

namespace Tests\Unit\Session\Parse\Grammar;

use MySqlMemory\Session\Parse\Grammar\KeywordCatalog;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\GrammarRelease;

#[CoversClass(KeywordCatalog::class)]
#[Medium]
final class KeywordCatalogTest extends TestCase
{
    public function testOfDerivesReservedWordsAndCatalogOnlyExceptions(): void
    {
        $current = array_column((new KeywordCatalog())->of(GrammarRelease::MySql847), 1, 0);
        $older = array_column((new KeywordCatalog())->of(GrammarRelease::MySql8044), 1, 0);
        self::assertSame([1, 0, 0, 1], [$current['SELECT'], $current['ACCOUNT'], $current['CUBE'], $older['CUBE']]);
        self::assertArrayNotHasKey('!=', $current);
        self::assertSame([], (new KeywordCatalog())->of(GrammarRelease::MySql5744));
    }
}
