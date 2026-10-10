<?php

declare(strict_types=1);

namespace MySqlMemory\Typing;

/**
 * Untailored Unicode 9.0 weights, derived from the public Unicode collation data.
 *
 * Explicit mappings retain eight collation elements per character. Implicit ideograph weights
 * follow UTS #10 revision 34. Black-box MySQL observations include the unassigned tails of
 * the Hangul and Tangut blocks; their weights follow the same block's arithmetic.
 * The last Hangul page (D700..D7AF) has tertiary weight 3 for every element in MySQL.
 * Every Unicode scalar was compared with MySQL 8.0.44, 8.4.7 and 9.1.0.
 * Source: https://www.unicode.org/reports/tr10/tr10-34.html#Implicit_Weights.
 * Data: https://www.unicode.org/Public/UCA/9.0.0/allkeys.txt.
 *
 * @visibility MySqlMemory
 */
final class UnicodeWeights
{
    /**
     * @var array<int, string>|null The encoded pages of explicit weights
     */
    private static ?array $encoded = null;

    /**
     * @var array<int, list<string>> The pages split into code-point entries as needed
     */
    private static array $pages = [];

    /**
     * Answers one Unicode scalar's level (1=primary, 2=secondary, 3=tertiary), in network byte order.
     */
    public function level(int $point, int $level): string
    {
        if (self::$encoded === null) {
            /** @var array<int, string> $encoded */
            $encoded = require dirname(__DIR__, 2) . '/resources/uca-900-weights.php';
            self::$encoded = $encoded;
        }
        $page = $point >> 8;
        if (isset(self::$encoded[$page])) {
            self::$pages[$page] ??= explode('|', self::$encoded[$page]);
            $hex = self::$pages[$page][$point & 255];
            if ($hex !== '-') {
                return (string) hex2bin(explode(':', $hex)[$level - 1]);
            }
        }
        if ($point >= 0xAC00 && $point <= 0xD7AF) {
            $syllable = $point - 0xAC00;
            if ($point >= 0xD700 && $level === 3) {
                return str_repeat("\0\3", $syllable % 28 === 0 ? 2 : 3);
            }
            $weight = $this->level(0x1100 + intdiv($syllable, 588), $level) . $this->level(0x1161 + intdiv($syllable % 588, 28), $level);

            return $weight . ($syllable % 28 === 0 ? '' : $this->level(0x11A7 + $syllable % 28, $level));
        }
        if ($level > 1) {
            return $level === 2 ? "\0\x20" : "\0\2";
        }
        if ($point >= 0x17000 && $point <= 0x18AFF) {
            return pack('n2', 0xFB00, ($point - 0x17000) | 0x8000);
        }
        $base = 0xFBC0;
        if (($point >= 0x4E00 && $point <= 0x9FD5) || in_array($point, [0xFA0E, 0xFA0F, 0xFA11, 0xFA13, 0xFA14, 0xFA1F, 0xFA21, 0xFA23, 0xFA24, 0xFA27, 0xFA28, 0xFA29], true)) {
            $base = 0xFB40;
        }
        foreach ([[0x3400, 0x4DB5], [0x20000, 0x2A6D6], [0x2A700, 0x2B734], [0x2B740, 0x2B81D], [0x2B820, 0x2CEA1]] as [$from, $to]) {
            if ($point >= $from && $point <= $to) {
                $base = 0xFB80;
            }
        }

        return pack('n2', $base + ($point >> 15), ($point & 0x7FFF) | 0x8000);
    }

    /**
     * Weighs each level of a string before separating the levels with a zero weight.
     *
     * @param list<string> $characters The input as UTF-8 characters
     */
    public function text(array $characters, int $levels): string
    {
        $result = [];
        for ($level = 1; $level <= $levels; ++$level) {
            $weight = '';
            foreach ($characters as $character) {
                $weight .= $this->level(mb_ord($character, 'UTF-8'), $level);
            }
            $result[] = $weight;
        }

        return implode("\0\0", $result);
    }

    /**
     * Answers the number of levels of an untailored Unicode 9.0 collation, or zero for another collation.
     */
    public function levels(string $collation): int
    {
        return match ($collation) {
            'utf8mb4_0900_ai_ci' => 1,
            'utf8mb4_0900_as_ci' => 2,
            'utf8mb4_0900_as_cs' => 3,
            default => 0,
        };
    }
}
