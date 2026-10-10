<?php

declare(strict_types=1);

namespace MySqlMemory\Value;

/**
 * Writes double-precision numbers as the server writes them in text.
 *
 * The shortest digits that read back as the same number are written in positional notation,
 * unless the point lies more than 15 digits after the first digit, more than 14 zeros follow the
 * point before the first digit, or the positional text takes more than 22 characters; then the
 * digits are written with an exponent (`1e15`, `1.2345678901234568e-5`).
 *
 * @visibility MySqlMemory
 */
final class Real
{
    /**
     * Writes a number in the text the server returns for a DOUBLE.
     *
     * An infinity, which ROUND past the largest double produces, is written as 0, as the server writes it.
     */
    public static function format(float $value): string
    {
        if (is_infinite($value)) {
            return '0';
        }
        if (is_nan($value)) {
            return 'nan';
        }
        if ($value === 0.0) {
            return fdiv(1, $value) < 0 ? '-0' : '0';
        }
        [$digits, $point] = self::digits(abs($value));
        $sign = $value < 0 ? '-' : '';
        $length = strlen($digits);
        $positional = $point <= 0 ? $length - $point + 2 : ($point < $length ? $length + 1 : $point);
        if ($point > 15 || $point <= -15 || $positional + strlen($sign) > 22) {
            $mantissa = $length > 1 ? $digits[0] . '.' . substr($digits, 1) : $digits;

            return $sign . $mantissa . 'e' . ($point - 1);
        }
        if ($point <= 0) {
            return $sign . '0.' . str_repeat('0', -$point) . $digits;
        }
        if ($point < $length) {
            return $sign . substr($digits, 0, $point) . '.' . substr($digits, $point);
        }

        return $sign . $digits . str_repeat('0', $point - $length);
    }

    /**
     * Answers the shortest significant digits of a positive number and the place of its point.
     *
     * The number is 0.DIGITS times ten to the power of the place.
     *
     * @return array{string, int}
     */
    public static function digits(float $value): array
    {
        $text = sprintf('%.16e', $value);
        for ($precision = 1; $precision <= 17; $precision++) {
            $text = sprintf('%.' . ($precision - 1) . 'e', $value);
            if ((float) $text === $value) {
                break;
            }
        }
        [$mantissa, $exponent] = explode('e', $text);
        $digits = rtrim(str_replace('.', '', $mantissa), '0');

        return [$digits === '' ? '0' : $digits, (int) $exponent + 1];
    }

    /**
     * Writes a number with a fixed number of decimals, rounding half away from zero.
     */
    public static function fixed(float $value, int $decimals): string
    {
        $text = number_format(round($value, $decimals), $decimals, '.', '');

        return $text === '-' . str_repeat('0', 1) . ($decimals > 0 ? '.' . str_repeat('0', $decimals) : '') ? substr($text, 1) : $text;
    }
}
