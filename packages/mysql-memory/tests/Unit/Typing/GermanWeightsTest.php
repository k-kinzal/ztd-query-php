<?php

declare(strict_types=1);

namespace Tests\Unit\Typing;

use MySqlMemory\Typing\GermanWeights;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation;

#[CoversClass(GermanWeights::class)]
#[Small]
final class GermanWeightsTest extends TestCase
{
    /**
     * @return array<string, array{int, string}>
     */
    public static function providerWeights(): array
    {
        return [
            'upper a umlaut' => [0xC4, '0e330e8b'],
            'upper ae' => [0xC6, '0e330e8b'],
            'upper o umlaut' => [0xD6, '0f820e8b'],
            'upper u umlaut' => [0xDC, '101f0e8b'],
            'a umlaut' => [0xE4, '0e330e8b'],
            'ae' => [0xE6, '0e330e8b'],
            'o umlaut' => [0xF6, '0f820e8b'],
            'u umlaut' => [0xFC, '101f0e8b'],
            'ordinary a' => [0x61, '0e33'],
            'combining diaeresis' => [0x308, ''],
            'a umlaut with macron' => [0x1DF, '0e33'],
            'sharp s' => [0xDF, '0fea0fea'],
            'later capital sharp s' => [0x1E9E, 'fbc09e9e'],
            'supplementary' => [0x1F600, 'fffd'],
        ];
    }

    #[DataProvider('providerWeights')]
    public function testPrimaryRetainsTheNativePhonebookExpansions(int $point, string $expected): void
    {
        self::assertSame($expected, bin2hex((new GermanWeights())->primary($point)));
    }

    public function testSupportsSeparatesUnicodeAndSingleByteCollations(): void
    {
        $weights = new GermanWeights();

        self::assertTrue($weights->supports(Collation::known('utf8mb4_german2_ci')));
        self::assertTrue($weights->supports(Collation::known('utf16_german2_ci')));
        self::assertFalse($weights->supports(Collation::known('latin1_german2_ci')));
        self::assertFalse($weights->supports(Collation::known('utf8mb4_unicode_ci')));
    }
}
