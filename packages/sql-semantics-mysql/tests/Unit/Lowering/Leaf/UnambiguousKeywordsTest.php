<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Leaf;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Lowering\Productions;
use SqlSemantics\Platform\MySql\Lowering\Leaf\UnambiguousKeywords;

#[CoversClass(UnambiguousKeywords::class)]
#[Medium]
final class UnambiguousKeywordsTest extends TestCase
{
    public function testSignaturesListEveryKeywordAsIdentifierProductionOfTheReleases(): void
    {
        $directory = dirname(__DIR__, 4) . '/resources/productions/';
        $paths = glob($directory . 'mysql-*.php');
        self::assertIsArray($paths);
        $signatures = array_merge(...array_map(static fn (string $path): array => Productions::load($path)->all(), $paths));
        $expected = array_values(array_unique(array_filter($signatures, static fn (string $signature): bool => str_starts_with($signature, 'ident_keywords_unambiguous: ') || str_starts_with($signature, 'ident_keywords_unambiguous: '))));
        $oldest = Productions::load($directory . 'mysql-8.0.44.php')->all();
        $newest = Productions::load($directory . 'mysql-9.1.0.php')->all();

        self::assertContains('ident_keywords_unambiguous: ACTION', UnambiguousKeywords::SIGNATURES);
        self::assertSame([], array_values(array_diff($expected, UnambiguousKeywords::SIGNATURES)));
        self::assertSame([], array_values(array_diff(UnambiguousKeywords::SIGNATURES, $expected)));
        self::assertSame([], array_values(array_filter(UnambiguousKeywords::SIGNATURES, static fn (string $signature): bool => substr_count($signature, ' ') !== 1)));
        self::assertContains('ident_keywords_unambiguous: ACTION', $oldest);
        self::assertContains('ident_keywords_unambiguous: ACTION', $newest);
    }
}
