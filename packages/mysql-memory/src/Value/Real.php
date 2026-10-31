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
        return \SqlSemantics\Platform\MySql\Rules\Literal\Real::format($value);
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
        return \SqlSemantics\Platform\MySql\Rules\Literal\Real::digits($value);
    }

    /**
     * Writes a number with a fixed number of decimals, rounding half away from zero.
     */
    public static function fixed(float $value, int $decimals): string
    {
        $text = number_format(round($value, $decimals), $decimals, '.', '');
        if ($value === 0.0 && fdiv(1.0, $value) === -INF) {
            return '-' . $text;
        }

        return $text === '-' . str_repeat('0', 1) . ($decimals > 0 ? '.' . str_repeat('0', $decimals) : '') ? substr($text, 1) : $text;
    }
}
