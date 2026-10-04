<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Leaf;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Lowering\Productions;
use SqlSemantics\Platform\MySql\Lowering\Leaf\AmbiguousKeywords;

#[CoversClass(AmbiguousKeywords::class)]
#[Medium]
final class AmbiguousKeywordsTest extends TestCase
{
    public function testSignaturesListEveryKeywordAsIdentifierProductionOfTheReleases(): void
    {
        $directory = dirname(__DIR__, 4) . '/resources/productions/';
        $paths = glob($directory . 'mysql-*.php');
        self::assertIsArray($paths);
        $signatures = array_merge(...array_map(static fn (string $path): array => Productions::load($path)->all(), $paths));
        $expected = array_values(array_unique(array_filter($signatures, static fn (string $signature): bool => str_starts_with($signature, 'ident_keywords_ambiguous_') || str_starts_with($signature, 'ident_keywords_ambiguous_'))));
        $oldest = Productions::load($directory . 'mysql-8.0.44.php')->all();
        $newest = Productions::load($directory . 'mysql-9.1.0.php')->all();

        self::assertContains('ident_keywords_ambiguous_2_labels: BEGIN_SYM', AmbiguousKeywords::SIGNATURES);
        self::assertSame([], array_values(array_diff($expected, AmbiguousKeywords::SIGNATURES)));
        self::assertSame([], array_values(array_diff(AmbiguousKeywords::SIGNATURES, $expected)));
        self::assertSame([], array_values(array_filter(AmbiguousKeywords::SIGNATURES, static fn (string $signature): bool => substr_count($signature, ' ') !== 1)));
        self::assertContains('ident_keywords_ambiguous_2_labels: BEGIN_SYM', $oldest);
        self::assertContains('ident_keywords_ambiguous_2_labels: BEGIN_SYM', $newest);
    }
}
