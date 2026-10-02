<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Leaf\Keyword;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Platform\PostgreSql\Lowering\Leaf\Keyword\TypeFunctionNameKeywords;
use SqlSemantics\Platform\PostgreSql\Platform;

#[CoversClass(TypeFunctionNameKeywords::class)]
#[Small]
final class TypeFunctionNameKeywordsTest extends TestCase
{
    public function testSignaturesAreTheKeywordProductionsOfBothReleases(): void
    {
        $platform = new Platform();
        $all = [...$platform->productions(new LanguageProfile(GrammarRelease::PostgreSql166))->all(), ...$platform->productions(new LanguageProfile(GrammarRelease::PostgreSql172))->all()];
        $expected = array_values(array_unique(array_filter($all, static fn (string $signature): bool => str_starts_with($signature, 'type_func_name_keyword: '))));
        sort($expected);
        self::assertSame($expected, TypeFunctionNameKeywords::SIGNATURES);
    }
}
