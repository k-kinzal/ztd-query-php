<?php

declare(strict_types=1);

namespace Tests\Unit\Typing;

use MySqlMemory\Typing\LegacyUnicodeWeights;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(LegacyUnicodeWeights::class)]
#[Small]
final class LegacyUnicodeWeightsTest extends TestCase
{
    /**
     * @return array<string, array{int, string}>
     */
    public static function providerWeights(): array
    {
        return [
            'ASCII' => [0x61, '120f'],
            'accent' => [0xE9, '126b'],
            'expansion' => [0xDF, '14101410'],
            'eight elements' => [0xFDFA, '18fc192b194f020a18ad192b192b193d'],
            'ignorable' => [0x301, ''],
            'core ideograph' => [0x4E00, 'fb40ce00'],
            'extension A' => [0x3400, 'fb80b400'],
            'compatibility form' => [0xFA0E, 'fb41fa0e'],
            'core boundary' => [0x9FA5, 'fb419fa5'],
            'later ideograph' => [0x9FA6, 'fbc19fa6'],
            'Hangul' => [0xAC00, 'fbc1ac00'],
            'extension B' => [0x20000, 'fbc48000'],
            'unassigned' => [0x378, 'fbc08378'],
            'last scalar' => [0x10FFFF, 'fbe1ffff'],
        ];
    }

    #[DataProvider('providerWeights')]
    public function testPrimaryRetainsLegacyExpansionsAndImplicitRanges(int $point, string $expected): void
    {
        self::assertSame($expected, bin2hex((new LegacyUnicodeWeights())->primary($point)));
    }
}
