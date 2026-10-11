<?php

declare(strict_types=1);

namespace MySqlMemory\Typing;

/**
 * Untailored Unicode 4.0 and 5.2 primary weights from the public Unicode collation data.
 *
 * SQL observations retain eight elements per character. Implicit Han weighting covers
 * BMP ideographs through 9FA5 and Extension A, together with twelve compatibility forms.
 * Hangul uses default implicit weights. Unicode 4.0 assigns FFFD to supplementary characters;
 * Unicode 5.2 retains their individual weights. Unicode 4.0 excludes expansions over eight elements.
 * Data: https://www.unicode.org/Public/UCA/5.2.0/allkeys.txt.
 * https://www.unicode.org/Public/UCA/4.0.0/allkeys-4.0.0.txt.
 *
 * @visibility MySqlMemory
 */
final class LegacyUnicodeWeights
{
    /**
     * @var array<int, array<int, string>> The encoded pages of explicit weights by Unicode version
     */
    private static array $encoded = [];

    /**
     * @var array<string, list<string>> The versioned pages split into code-point entries as needed
     */
    private static array $pages = [];

    /**
     * @param bool $unicode52 Whether to use Unicode 5.2 instead of Unicode 4.0
     */
    public function __construct(public readonly bool $unicode52 = true)
    {
    }

    /**
     * Answers one Unicode scalar's primary weights, in network byte order.
     */
    public function primary(int $point): string
    {
        if (!$this->unicode52 && $point > 0xFFFF) {
            return "\xff\xfd";
        }
        $version = $this->unicode52 ? 520 : 400;
        if (!isset(self::$encoded[$version])) {
            /** @var array<int, string> $encoded */
            $encoded = require dirname(__DIR__, 2) . '/resources/uca-' . $version . '-primary.php';
            self::$encoded[$version] = $encoded;
        }
        $page = $point >> 8;
        $key = $version . ':' . $page;
        if (isset(self::$encoded[$version][$page])) {
            self::$pages[$key] ??= explode('|', self::$encoded[$version][$page]);
            $hex = self::$pages[$key][$point & 255];
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
