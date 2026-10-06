<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Leaf;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Lowering\Productions;
use SqlSemantics\Platform\MySql\Lowering\Leaf\LegacyKeywords;

#[CoversClass(LegacyKeywords::class)]
#[Medium]
final class LegacyKeywordsTest extends TestCase
{
    public function testSignaturesListEveryKeywordAsIdentifierProductionOfTheReleases(): void
    {
        $directory = dirname(__DIR__, 4) . '/resources/productions/';
        $paths = glob($directory . 'mysql-*.php');
        self::assertIsArray($paths);
        $signatures = array_merge(...array_map(static fn (string $path): array => Productions::load($path)->all(), $paths));
        $expected = array_values(array_unique(array_filter($signatures, static fn (string $signature): bool => (str_starts_with($signature, 'keyword: ') || str_starts_with($signature, 'keyword_sp: ')) && $signature !== 'keyword: keyword_sp')));
        $oldest = Productions::load($directory . 'mysql-5.6.51.php')->all();
        $newest = Productions::load($directory . 'mysql-5.7.44.php')->all();

        self::assertContains('keyword: ASCII_SYM', LegacyKeywords::SIGNATURES);
        self::assertSame([], array_values(array_diff($expected, LegacyKeywords::SIGNATURES)));
        self::assertSame([], array_values(array_diff(LegacyKeywords::SIGNATURES, $expected)));
        self::assertSame([], array_values(array_filter(LegacyKeywords::SIGNATURES, static fn (string $signature): bool => substr_count($signature, ' ') !== 1)));
        self::assertContains('keyword: ASCII_SYM', $oldest);
        self::assertContains('keyword: ASCII_SYM', $newest);
    }
}
