<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Leaf\Keyword;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Platform\PostgreSql\Lowering\Leaf\Keyword\UnreservedKeywords;
use SqlSemantics\Platform\PostgreSql\Platform;

#[CoversClass(UnreservedKeywords::class)]
#[Small]
final class UnreservedKeywordsTest extends TestCase
{
    public function testSignaturesAreTheKeywordProductionsOfBothReleases(): void
    {
        $platform = new Platform();
        $all = [...$platform->productions(new LanguageProfile(GrammarRelease::PostgreSql166))->all(), ...$platform->productions(new LanguageProfile(GrammarRelease::PostgreSql172))->all()];
        $expected = array_values(array_unique(array_filter($all, static fn (string $signature): bool => str_starts_with($signature, 'unreserved_keyword: '))));
        sort($expected);
        self::assertSame($expected, UnreservedKeywords::SIGNATURES);
    }
}
