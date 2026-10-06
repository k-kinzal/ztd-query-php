<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules;

/**
 * Reads and spells the digits of a hexadecimal or bit literal token.
 *
 * Rule: MYSQL-RADIX-DECODE-001. Scope: the terminals HEX_NUM and BIN_NUM.
 * Both spellings of each literal, `X'1F'` and `0x1F`, `b'101'` and `0b101`,
 * denote the same value: the digits between the quotes or after the prefix.
 * The digits are kept exactly, leading zeros and letter case included.
 * Terminates: constant work per token.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/hexadecimal-literals.html,
 * https://dev.mysql.com/doc/refman/8.4/en/bit-value-literals.html.
 * Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class RadixSpelling
{
    /**
     * Answers the digits of a hexadecimal or bit literal token text.
     */
    public function digits(string $text): string
    {
        return ($text[0] ?? '') === '0' ? substr($text, 2) : substr($text, 2, -1);
    }

    /**
     * Spells hexadecimal digits: quoted when their count is even, with the `0x` prefix otherwise.
     *
     * The quoted spelling requires an even count and is the only spelling of
     * no digits at all; the prefixed spelling reads an odd count as having a
     * leading zero. Either spelling answers the same digits to digits().
     */
    public function hexadecimal(string $digits): string
    {
        return strlen($digits) % 2 === 0 ? "x'" . $digits . "'" : '0x' . $digits;
    }

    /**
     * Spells bit digits in the quoted spelling, which holds any count of digits.
     */
    public function bits(string $digits): string
    {
        return "b'" . $digits . "'";
    }
}
