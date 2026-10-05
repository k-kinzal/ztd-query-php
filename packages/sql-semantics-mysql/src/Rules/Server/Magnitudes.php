<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Server;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Statement\Literal\Numeral;
use SqlSemantics\Platform\MySql\Statement\Literal\Radix;
use SqlSemantics\Platform\MySql\Statement\Literal\Text;
use SqlSemantics\Platform\MySql\Statement\Server\Problem\FractionalNumber;
use SqlSemantics\Platform\MySql\Statement\Server\Transaction\Xa\Xid;

/**
 * Measures the operands whose size the server checks while it parses a server administration statement.
 *
 * Rule: MYSQL-SERVER-MAGNITUDE-001. The length of a string operand is its
 * byte count: the decoded bytes of a quoted string, half the hexadecimal
 * digits rounded up, an eighth of the bits rounded up. A number at a
 * position that is not an expression is converted as the server converts
 * it: a hexadecimal literal by its digits, clamped to 2^63-1 as strtoll
 * clamps it, any other number by the decimal digits it starts with
 * (my_strtoll10 stops at a point or an exponent). The format identifier of
 * an XA transaction identifier is refused above 2^63-1 from 5.7 on
 * (`format_id_overflow_detected` in the `xid` action); 5.6 takes any. The
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
     * The largest signed 64-bit integer, the value strtoll gives a larger hexadecimal number.
     */
    public const LARGEST = '9223372036854775807';

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
     * Tells whether a release reads an XA format identifier: any number on 5.6, at most 2^63-1 later.
     */
    public function format(Numeral $format, GrammarRelease $release): bool
    {
        return $release === GrammarRelease::MySql5651 || $this->atMost($format, self::LARGEST);
    }

    /**
     * Refuses an XA transaction identifier whose format identifier the release rejects while it parses.
     *
     * @throws \SqlSemantics\Diagnostic\InvalidConstruction When the format identifier exceeds what the release reads
     */
    public function identifier(Xid $xid, GrammarRelease $release): void
    {
        Check::input($xid->format === null || $this->format($xid->format, $release), 'A format identifier is at most 9223372036854775807 from MySQL 5.7 on.');
    }

    /**
     * Tells whether a number the server converts to an unsigned integer is at most a limit given in decimal digits.
     */
    public function atMost(Numeral $number, string $limit): bool
    {
        $digits = ltrim($number->hexadecimal ? $this->decimal($number->text) : (string) preg_replace('/\A([0-9]*).*\z/s', '$1', $number->text), '0');
        if ($number->hexadecimal && (strlen($digits) > 19 || (strlen($digits) === 19 && strcmp($digits, self::LARGEST) > 0))) {
            $digits = self::LARGEST;
        }

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
