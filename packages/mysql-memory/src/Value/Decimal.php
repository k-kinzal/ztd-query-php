<?php

declare(strict_types=1);

namespace MySqlMemory\Value;

/**
 * Exact decimal arithmetic on canonical decimal text.
 *
 * A decimal is written with an optional minus sign, the integer digits without leading zeros
 * (at least one), and, when the scale is positive, a point and exactly that many digits.
 * Rounding is half away from zero, as the server rounds exact values.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/precision-math-rounding.html.
 *
 * @visibility MySqlMemory
 */
final class Decimal
{
    /**
     * The most digits a DECIMAL holds.
     */
    public const MAX_PRECISION = 65;

    /**
     * Answers the number of digits after the point of a decimal text.
     */
    public static function scale(string $value): int
    {
        $point = strpos($value, '.');

        return $point === false ? 0 : strlen($value) - $point - 1;
    }

    /**
     * Answers the number of digits before the point of a decimal text, at least one.
     */
    public static function integerDigits(string $value): int
    {
        $integer = ltrim(explode('.', ltrim($value, '-'))[0], '0');

        return max(1, strlen($integer));
    }

    /**
     * Rounds a decimal text, half away from zero, to a scale and writes it canonically.
     */
    public static function round(string $value, int $scale): string
    {
        $value = self::canonical($value);
        $current = self::scale($value);
        if ($scale >= $current) {
            return self::canonical(bcadd($value, '0', max(0, $scale)));
        }
        $half = '0.' . str_repeat('0', max(0, $scale)) . '5';
        if ($scale < 0) {
            $half = '5' . str_repeat('0', -$scale - 1);
        }
        $shifted = str_starts_with($value, '-') ? bcsub($value, $half, $current) : bcadd($value, $half, $current);
        if ($scale >= 0) {
            return self::canonical(bcadd($shifted, '0', $scale));
        }
        $unit = '1' . str_repeat('0', -$scale);

        return self::canonical(bcmul(bcdiv($shifted, $unit, 0), $unit, 0));
    }

    /**
     * Truncates a decimal text toward zero to a scale.
     */
    public static function truncate(string $value, int $scale): string
    {
        if ($scale >= 0) {
            return self::canonical(bcadd(self::canonical($value), '0', $scale));
        }
        $unit = '1' . str_repeat('0', -$scale);

        return self::canonical(bcmul(bcdiv(self::canonical($value), $unit, 0), $unit, 0));
    }

    /**
     * Adds two decimals; the scale is the larger scale.
     */
    public static function add(string $left, string $right): string
    {
        return self::canonical(bcadd($left, $right, max(self::scale($left), self::scale($right))));
    }

    /**
     * Subtracts a decimal from another; the scale is the larger scale.
     */
    public static function subtract(string $left, string $right): string
    {
        return self::canonical(bcsub($left, $right, max(self::scale($left), self::scale($right))));
    }

    /**
     * Multiplies two decimals; the scale is the sum of the scales, at most 30.
     */
    public static function multiply(string $left, string $right): string
    {
        $scale = self::scale($left) + self::scale($right);

        return self::round(bcmul($left, $right, $scale), min(30, $scale));
    }

    /**
     * Divides a decimal by another and rounds the quotient to a scale; null for a zero divisor.
     */
    public static function divide(string $left, string $right, int $scale): ?string
    {
        if (bccomp($right, '0', self::scale($right)) === 0) {
            return null;
        }

        return self::round(bcdiv($left, $right, $scale + 1), $scale);
    }

    /**
     * Answers the remainder of a division, with the sign of the dividend; null for a zero divisor.
     */
    public static function modulo(string $left, string $right): ?string
    {
        $scale = max(self::scale($left), self::scale($right));
        if (bccomp($right, '0', $scale) === 0) {
            return null;
        }

        return self::canonical(bcmod($left, $right, $scale));
    }

    /**
     * Compares two decimals: -1, 0 or 1.
     */
    public static function compare(string $left, string $right): int
    {
        return bccomp($left, $right, max(self::scale($left), self::scale($right)));
    }

    /**
     * Negates a decimal.
     */
    public static function negate(string $value): string
    {
        return self::canonical(str_starts_with($value, '-') ? substr($value, 1) : '-' . $value);
    }

    /**
     * Writes a decimal text canonically: no plus sign, no leading zeros, no negative zero.
     */
    public static function canonical(string $value): string
    {
        $negative = str_starts_with($value, '-');
        $value = ltrim($value, '+-');
        $parts = explode('.', $value, 2);
        $integer = ltrim($parts[0], '0');
        $integer = $integer === '' ? '0' : $integer;
        $text = isset($parts[1]) && $parts[1] !== '' ? $integer . '.' . $parts[1] : $integer;
        if ($negative && trim($text, '0.') !== '') {
            return '-' . $text;
        }

        return $text;
    }

    /**
     * Writes an integer, read as unsigned when asked, as a decimal.
     */
    public static function fromInteger(int $value, bool $unsigned = false): string
    {
        if ($unsigned && $value < 0) {
            return bcadd((string) $value, '18446744073709551616', 0);
        }

        return (string) $value;
    }

    /**
     * Writes a double as a decimal with the digits of its shortest text.
     */
    public static function fromDouble(float $value): string
    {
        if ($value === 0.0) {
            return '0';
        }
        [$digits, $point] = Real::digits(abs($value));
        $sign = $value < 0 ? '-' : '';
        $length = strlen($digits);
        if ($point <= 0) {
            return $sign . '0.' . str_repeat('0', -$point) . $digits;
        }
        if ($point < $length) {
            return $sign . substr($digits, 0, $point) . '.' . substr($digits, $point);
        }

        return $sign . $digits . str_repeat('0', $point - $length);
    }
}
