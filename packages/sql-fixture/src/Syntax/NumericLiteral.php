<?php

declare(strict_types=1);

namespace SqlFixture\Syntax;

/**
 * Converts the text of a numeric token into the PHP number it denotes.
 *
 * The dialects write a number in more ways than PHP reads one: digits may be
 * grouped with underscores, and a radix may be written in front of them.
 *
 * @visibility root
 */
final class NumericLiteral
{
    /**
     * Returns an integer for whole numbers that fit the platform, and a float otherwise.
     */
    public function decode(string $text): int|float
    {
        $digits = str_replace('_', '', $text);
        $sign = 1;
        if (str_starts_with($digits, '-') || str_starts_with($digits, '+')) {
            $sign = $digits[0] === '-' ? -1 : 1;
            $digits = substr($digits, 1);
        }
        $radix = match (strtolower(substr($digits, 0, 2))) {
            '0x' => 16,
            '0o' => 8,
            '0b' => 2,
            default => null,
        };
        if ($radix !== null) {
            return $sign * intval(substr($digits, 2), $radix);
        }
        $integer = filter_var($digits, FILTER_VALIDATE_INT);
        if ($integer !== false) {
            return $sign * $integer;
        }
        $whole = $this->whole($digits);

        return $whole ?? $sign * (float) $digits;
    }

    /**
     * Answers the integer a run of digits written with leading zeros denotes, or null when it is not one.
     */
    public function whole(string $digits): ?int
    {
        if (!ctype_digit($digits)) {
            return null;
        }
        $trimmed = ltrim($digits, '0');
        $trimmed = $trimmed === '' ? '0' : $trimmed;
        $value = (int) $trimmed;

        return (string) $value === $trimmed ? $value : null;
    }
}
