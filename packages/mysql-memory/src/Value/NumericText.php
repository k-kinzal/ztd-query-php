<?php

declare(strict_types=1);

namespace MySqlMemory\Value;

/**
 * Reads the number at the start of a string, as the server reads a string in a numeric context.
 *
 * Leading whitespace is skipped. The longest prefix that is a number is read; the text is
 * complete when nothing but spaces follows it. An empty string reads as zero and is complete.
 *
 * @visibility MySqlMemory
 */
final class NumericText
{
    /**
     * @param string $number The prefix read as a number, in canonical form; '0' when there is none
     * @param bool $complete Whether the whole string was a number, apart from surrounding spaces
     */
    public function __construct(public readonly string $number, public readonly bool $complete)
    {
    }

    /**
     * Reads the prefix as a floating-point number: digits, a point, and an exponent.
     */
    public static function real(string $text): self
    {
        $trimmed = ltrim($text, " \t\n\r\v\f");
        if ($trimmed === '') {
            return new self('0', true);
        }
        if (preg_match('/\A[+-]?(?:[0-9]+(?:\.[0-9]*)?|\.[0-9]+)(?:[eE][+-]?[0-9]+)?/', $trimmed, $match) !== 1) {
            return new self('0', false);
        }

        return new self($match[0], rtrim(substr($trimmed, strlen($match[0])), ' ') === '');
    }

    /**
     * Reads the prefix as an exact number: digits, a point, and digits; an exponent is applied.
     */
    public static function exact(string $text): self
    {
        $real = self::real($text);
        if (!str_contains(strtolower($real->number), 'e')) {
            return new self(Decimal::canonical($real->number === '' ? '0' : rtrim($real->number, '.')), $real->complete);
        }
        [$mantissa, $exponent] = explode('e', strtolower($real->number));
        $shift = (int) $exponent;
        $scale = max(0, Decimal::scale($mantissa) - $shift);
        $factor = bcpow('10', (string) abs($shift), 0);
        $value = $shift >= 0 ? bcmul(Decimal::numeric(rtrim($mantissa, '.')), $factor, $scale) : bcdiv(rtrim($mantissa, '.'), $factor, $scale);

        return new self(Decimal::canonical($value), $real->complete);
    }

    /**
     * Reads the prefix as an integer: an optional sign and digits, stopping at a point.
     */
    public static function integer(string $text): self
    {
        $trimmed = ltrim($text, " \t\n\r\v\f");
        if ($trimmed === '') {
            return new self('0', true);
        }
        if (preg_match('/\A[+-]?[0-9]+/', $trimmed, $match) !== 1) {
            return new self('0', false);
        }

        return new self(Decimal::canonical($match[0]), rtrim(substr($trimmed, strlen($match[0])), ' ') === '');
    }
}
