<?php

declare(strict_types=1);

namespace MySqlMemory\Value;

/**
 * 64-bit integers held in PHP ints, read as signed or as unsigned.
 *
 * An unsigned value above PHP_INT_MAX is held as the signed int with the same 64 bits.
 *
 * @visibility MySqlMemory
 */
final class Integer
{
    /**
     * The largest BIGINT UNSIGNED.
     */
    public const UNSIGNED_MAX = '18446744073709551615';

    /**
     * Writes an int, read as unsigned when asked, as decimal text.
     */
    public static function text(int $value, bool $unsigned): string
    {
        return $unsigned && $value < 0 ? bcadd((string) $value, '18446744073709551616', 0) : (string) $value;
    }

    /**
     * Answers an int, read as unsigned when asked, as a double.
     */
    public static function real(int $value, bool $unsigned): float
    {
        return $unsigned && $value < 0 ? (float) $value + 18446744073709551616.0 : (float) $value;
    }

    /**
     * Answers the int holding an integer text that lies in the unsigned range.
     */
    public static function fromUnsignedText(string $text): int
    {
        return bccomp($text, (string) PHP_INT_MAX, 0) > 0 ? (int) bcsub($text, '18446744073709551616', 0) : (int) $text;
    }

    /**
     * Tells whether an integer text lies in the signed BIGINT range.
     */
    public static function signedRange(string $text): bool
    {
        return bccomp($text, '-9223372036854775808', 0) >= 0 && bccomp($text, '9223372036854775807', 0) <= 0;
    }

    /**
     * Tells whether an integer text lies in the unsigned BIGINT range.
     */
    public static function unsignedRange(string $text): bool
    {
        return bccomp($text, '0', 0) >= 0 && bccomp($text, self::UNSIGNED_MAX, 0) <= 0;
    }

    /**
     * Compares two ints, each read as signed or unsigned: -1, 0 or 1.
     */
    public static function compare(int $left, bool $leftUnsigned, int $right, bool $rightUnsigned): int
    {
        if ($leftUnsigned === $rightUnsigned) {
            return $leftUnsigned ? ($left ^ PHP_INT_MIN) <=> ($right ^ PHP_INT_MIN) : $left <=> $right;
        }
        if ($leftUnsigned && $left < 0) {
            return 1;
        }
        if ($rightUnsigned && $right < 0) {
            return -1;
        }

        return $left <=> $right;
    }

    /**
     * Rounds a double to the nearest int, ties to even, saturating at the bounds of the range.
     */
    public static function fromReal(float $value, bool $unsigned): int
    {
        $rounded = round($value, 0, PHP_ROUND_HALF_EVEN);
        if ($unsigned) {
            if ($rounded <= 0) {
                return 0;
            }

            return $rounded >= 18446744073709551615.0 ? -1 : self::fromUnsignedText(sprintf('%.0f', $rounded));
        }
        if ($rounded >= 9223372036854775807.0) {
            return PHP_INT_MAX;
        }

        return $rounded <= -9223372036854775808.0 ? PHP_INT_MIN : (int) $rounded;
    }
}
