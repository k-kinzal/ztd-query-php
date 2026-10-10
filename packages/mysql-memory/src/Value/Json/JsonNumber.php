<?php

declare(strict_types=1);

namespace MySqlMemory\Value\Json;

/**
 * Reads a number of a JSON text as the server does, at the position a reading has reached ({@see Json}).
 *
 * A number is an optional minus, an integer part without leading zeros, an optional fraction and
 * an optional exponent. A number without a fraction or an exponent that fits a signed or unsigned
 * 64-bit integer is an integer (`-0` is `0`); every other number is a double, written as
 * {@see Json::double()} says, and refused when it is too big for a double.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/json.html.
 *
 * @visibility MySqlMemory
 */
final class JsonNumber
{
    /**
     * @param Json $json The reading, whose position is at the number and is moved past it
     */
    public function __construct(public readonly Json $json)
    {
    }

    /**
     * Reads the number at the position and answers it in the text the server writes.
     *
     * @example A double
     *     (new \MySqlMemory\Value\Json\JsonNumber(new \MySqlMemory\Value\Json\Json('1.50')))->read() // => '1.5'
     *
     * @throws JsonSyntax When the number is malformed or too big for a double
     */
    public function read(): string
    {
        $json = $this->json;
        $text = $json->text;
        $start = $json->at;
        if ($text[$json->at] === '-') {
            $json->at++;
        }
        $integer = strspn($text, '0123456789', $json->at);
        if ($integer === 0) {
            throw new JsonSyntax('Invalid value.', $json->at);
        }
        $json->at += $text[$json->at] === '0' ? 1 : $integer;
        $fraction = 0;
        $exponent = null;
        if (($text[$json->at] ?? '') === '.') {
            $json->at++;
            $fraction = strspn($text, '0123456789', $json->at);
            if ($fraction === 0) {
                throw new JsonSyntax('Miss fraction part in number.', $json->at);
            }
            $json->at += $fraction;
        }
        if (in_array($text[$json->at] ?? '', ['e', 'E'], true)) {
            $exponent = $this->exponent();
        }
        $written = substr($text, $start, $json->at - $start);
        if ($fraction === 0 && $exponent === null && self::integral($written)) {
            return $written === '-0' ? '0' : $written;
        }
        $value = (float) $written;
        if (($exponent !== null && $exponent > 308 + $fraction) || is_infinite($value)) {
            throw new JsonSyntax('Number too big to be stored in double.', $start);
        }

        return Json::double($value);
    }

    /**
     * Reads the exponent of a number from its `e` or `E`, and answers its value.
     *
     * An exponent of more than nine significant digits reads as 999999999, with its sign.
     *
     * @example A negative exponent
     *     (new \MySqlMemory\Value\Json\JsonNumber(new \MySqlMemory\Value\Json\Json('e-012')))->exponent() // => -12
     *
     * @throws JsonSyntax When no digit follows the `e` and its sign
     */
    public function exponent(): int
    {
        $json = $this->json;
        $text = $json->text;
        $json->at++;
        $sign = in_array($text[$json->at] ?? '', ['+', '-'], true) ? $text[$json->at++] : '+';
        $digits = strspn($text, '0123456789', $json->at);
        if ($digits === 0) {
            throw new JsonSyntax('Miss exponent in number.', $json->at);
        }
        $magnitude = ltrim(substr($text, $json->at, $digits), '0');
        $json->at += $digits;

        return (strlen($magnitude) > 9 ? 999999999 : (int) $magnitude) * ($sign === '-' ? -1 : 1);
    }

    /**
     * Tells whether an integer, as written, fits a signed 64-bit integer when negative or an unsigned one otherwise.
     *
     * @example One more than the largest unsigned integer
     *     \MySqlMemory\Value\Json\JsonNumber::integral('18446744073709551616') // => false
     */
    public static function integral(string $written): bool
    {
        $magnitude = ltrim($written, '-');
        $limit = $written[0] === '-' ? '9223372036854775808' : '18446744073709551615';

        return strlen($magnitude) < strlen($limit) || (strlen($magnitude) === strlen($limit) && strcmp($magnitude, $limit) <= 0);
    }
}
