<?php

declare(strict_types=1);

namespace MySqlMemory\Typing;

/**
 * Untailored Unicode 5.2 primary weights from the public Unicode collation data.
 *
 * SQL observations retain eight elements per character. Implicit Han weighting covers
 * BMP ideographs through 9FA5 and Extension A, together with twelve compatibility forms.
 * Hangul and supplementary ideographs use the default implicit weights. Every Unicode
 * scalar was compared with MySQL 8.0.44 and 9.1.0.
 * Data: https://www.unicode.org/Public/UCA/5.2.0/allkeys.txt.
 *
 * @visibility MySqlMemory
 */
final class LegacyUnicodeWeights
{
    /**
     * @var array<int, string>|null The encoded pages of explicit primary weights
     */
    private static ?array $encoded = null;

    /**
     * @var array<int, list<string>> The pages split into code-point entries as needed
     */
    private static array $pages = [];

    /**
     * Answers one Unicode scalar's primary weights, in network byte order.
     */
    public function primary(int $point): string
    {
        if (self::$encoded === null) {
            /** @var array<int, string> $encoded */
            $encoded = require dirname(__DIR__, 2) . '/resources/uca-520-primary.php';
            self::$encoded = $encoded;
        }
        $page = $point >> 8;
        if (isset(self::$encoded[$page])) {
            self::$pages[$page] ??= explode('|', self::$encoded[$page]);
            $hex = self::$pages[$page][$point & 255];
            if ($hex !== '-') {
                return (string) hex2bin($hex);
            }
        }
        $base = 0xFBC0;
        if (($point >= 0x4E00 && $point <= 0x9FA5) || in_array($point, [0xFA0E, 0xFA0F, 0xFA11, 0xFA13, 0xFA14, 0xFA1F, 0xFA21, 0xFA23, 0xFA24, 0xFA27, 0xFA28, 0xFA29], true)) {
            $base = 0xFB40;
        } elseif ($point >= 0x3400 && $point <= 0x4DB5) {
            $base = 0xFB80;
        }

        return pack('n2', $base + ($point >> 15), ($point & 0x7FFF) | 0x8000);
    }
}
