<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Server;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\MySql\Statement\Literal\Numeral;
use SqlSemantics\Platform\MySql\Statement\Literal\Radix;
use SqlSemantics\Platform\MySql\Statement\Literal\Text;
use SqlSemantics\Platform\MySql\Statement\Server\Problem\FractionalNumber;

/**
 * Measures the operands whose size the server checks while it parses a server administration statement.
 *
 * Rule: MYSQL-SERVER-MAGNITUDE-001. The length of a string operand is its
 * byte count: the decoded bytes of a quoted string, half the hexadecimal
 * digits rounded up, an eighth of the bits rounded up. A number at a
 * position that is not an expression is converted as the server converts
 * it: a hexadecimal literal by its digits, any other number by the decimal
 * digits it starts with (my_strtoll10 stops at a point or an exponent). The
 * comparison is exact on the digit strings, without a PHP number. At a
 * position the grammar reads through dec_num_error a decimal or floating
 * number is FractionalNumber (ER_ONLY_INTEGERS_ALLOWED).
 * Terminates: one pass over the digits.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/number-literals.html,
 * https://dev.mysql.com/doc/refman/8.4/en/hexadecimal-literals.html.
 * Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class Magnitudes
{
    /**
     * Answers the number of bytes a string operand holds.
     */
    public function bytes(Text $text): int
    {
        return match ($text->radix) {
            null => strlen($text->value),
            Radix::Hexadecimal => intdiv(strlen($text->value) + 1, 2),
            Radix::Bit => intdiv(strlen($text->value) + 7, 8),
        };
    }

    /**
     * Tells whether a number is an integer: digits only, or a hexadecimal literal.
     */
    public function integral(Numeral $number): bool
    {
        return $number->hexadecimal || preg_match('/\A[0-9]+\z/', $number->text) === 1;
    }

    /**
     * Reports a decimal or floating number at a position that reads it through dec_num_error.
     */
    public function integer(Derivation $derivation, Numeral $number): bool
    {
        if ($this->integral($number)) {
            return true;
        }
        $derivation->report(new FractionalNumber($number));

        return false;
    }

    /**
     * Tells whether a number the server converts to an unsigned integer is at most a limit given in decimal digits.
     */
    public function atMost(Numeral $number, string $limit): bool
    {
        $digits = $number->hexadecimal ? $this->decimal($number->text) : (string) preg_replace('/\A([0-9]*).*\z/s', '$1', $number->text);
        $digits = ltrim($digits, '0');

        return strlen($digits) < strlen($limit) || (strlen($digits) === strlen($limit) && strcmp($digits, $limit) <= 0);
    }

    /**
     * Converts hexadecimal digits into decimal digits exactly.
     */
    public function decimal(string $hexadecimal): string
    {
        $decimal = [0];
        $digits = ltrim($hexadecimal, '0');
        foreach (str_split($digits === '' ? '0' : $digits) as $digit) {
            $carry = (int) hexdec($digit);
            foreach ($decimal as $index => $value) {
                $product = $value * 16 + $carry;
                $decimal[$index] = $product % 10;
                $carry = intdiv($product, 10);
            }
            while ($carry > 0) {
                $decimal[] = $carry % 10;
                $carry = intdiv($carry, 10);
            }
        }

        return implode('', array_reverse($decimal));
    }
}
