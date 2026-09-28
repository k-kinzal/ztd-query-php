<?php

declare(strict_types=1);

namespace SqlSemantics\Core\Literal;

/**
 * Exact conversions of literal digits, without machine-number rounding.
 * @visibility SqlSemantics
 */
final class Encoding
{
    /**
     * Decodes hexadecimal digits to bytes.
     * @throws DecodingException When digits are invalid
     */
    public static function hex(string $digits): string
    {
        if (strspn($digits, '0123456789abcdefABCDEF') !== strlen($digits)) {
            throw new DecodingException('Invalid hexadecimal literal.');
        }
        $bytes = hex2bin(strlen($digits) % 2 === 0 ? $digits : '0' . $digits);
        if ($bytes === false) {
            throw new DecodingException('Invalid hexadecimal literal.');
        }
        return $bytes;
    }

    /**
     * Decodes binary or hexadecimal digits to an exact bit string.
     * @throws DecodingException When digits are invalid
     */
    public static function bits(string $digits, bool $hex = false): string
    {
        if (!$hex || $digits === '') {
            if (strspn($digits, '01') !== strlen($digits)) {
                throw new DecodingException('Invalid bit literal.');
            }
            return $digits;
        }
        self::hex($digits);
        return implode('', array_map(static fn (string $digit): string => str_pad(decbin((int) hexdec($digit)), 4, '0', STR_PAD_LEFT), str_split($digits)));
    }

    /**
     * Packs a bit string into bytes, padding the high bits with zeros.
     * @throws DecodingException When digits are invalid
     */
    public static function bytes(string $bits): string
    {
        $bits = self::bits($bits);
        if ($bits === '') {
            return '';
        }
        $padded = str_pad($bits, (int) (ceil(strlen($bits) / 8) * 8), '0', STR_PAD_LEFT);
        return implode('', array_map(static fn (string $byte): string => chr((int) bindec($byte)), str_split($padded, 8)));
    }

    /**
     * Converts radix-prefixed integers to exact decimal text.
     * @throws DecodingException When radix digits are invalid
     */
    public static function number(string $text): string
    {
        $text = str_replace('_', '', $text);
        $base = match (strtolower(substr($text, 0, 2))) {
            '0x' => 16, '0o' => 8, '0b' => 2, default => 10
        };
        if ($base === 10) {
            return $text;
        }
        $allowed = substr('0123456789abcdef', 0, $base);
        $digits = strtolower(substr($text, 2));
        if ($digits === '' || strspn($digits, $allowed) !== strlen($digits)) {
            throw new DecodingException('Invalid radix literal.');
        }
        $decimal = '0';
        foreach (str_split(substr($text, 2)) as $digit) {
            $carry = (int) hexdec($digit);
            $next = '';
            for ($i = strlen($decimal) - 1; $i >= 0; --$i) {
                $part = (int) $decimal[$i] * $base + $carry;
                $next = (string) ($part % 10) . $next;
                $carry = intdiv($part, 10);
            }
            $decimal = ($carry === 0 ? '' : (string) $carry) . $next;
        }
        $trimmed = ltrim($decimal, '0');
        return $trimmed === '' ? '0' : $trimmed;
    }

    /**
     * Adds one to nonnegative decimal digits without a machine integer conversion.
     * @throws DecodingException When the text does not contain decimal digits
     */
    public static function successor(string $digits): string
    {
        if ($digits === '' || !ctype_digit($digits)) {
            throw new DecodingException('Expected nonnegative decimal digits.');
        }
        for ($i = strlen($digits) - 1; $i >= 0; --$i) {
            if ($digits[$i] !== '9') {
                $digits[$i] = (string) ((int) $digits[$i] + 1);
                return $digits;
            }
            $digits[$i] = '0';
        }
        return '1' . $digits;
    }
}
