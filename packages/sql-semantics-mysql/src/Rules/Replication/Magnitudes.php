<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Replication;

use SqlSemantics\Platform\MySql\Statement\Literal\Numeral;

/**
 * Reads the value the server takes from a number written at an option position.
 *
 * Rule: MYSQL-REPLICATION-NUMBER-001. The rule ulong_num takes the integer
 * prefix of a decimal or floating number (my_strtoll10 stops at the first
 * character that is not a digit) and the value of a hexadecimal literal.
 * The rules real_ulong_num and real_ulonglong_num refuse a decimal or
 * floating number (dec_num_error, ER_ONLY_INTEGERS_ALLOWED). An integer
 * beyond the PHP integer range is reported as beyond every bound the checks
 * use. Terminates: constant work over the digits.
 * Source: sql/sql_yacc.yy (ulong_num, real_ulong_num, dec_num_error),
 * https://dev.mysql.com/doc/refman/8.4/en/number-literals.html.
 * Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class Magnitudes
{
    /**
     * Answers the integer value of a number, or null when it exceeds the PHP integer range.
     */
    public function integer(Numeral $number): ?int
    {
        if ($number->hexadecimal) {
            $digits = ltrim($number->text, '0');

            return strlen($digits) > 15 ? null : (int) hexdec($digits === '' ? '0' : $digits);
        }
        $digits = ltrim((string) preg_replace('/[^0-9].*\z/s', '', $number->text), '0');

        return strlen($digits) > 18 ? null : (int) $digits;
    }

    /**
     * Tells whether a number is greater than a bound.
     */
    public function exceeds(Numeral $number, int $bound): bool
    {
        $value = $this->integer($number);

        return $value === null || $value > $bound;
    }

    /**
     * Tells whether a number is 0 or 1.
     */
    public function flag(Numeral $number): bool
    {
        $value = $this->integer($number);

        return $value === 0 || $value === 1;
    }

    /**
     * Tells whether a number is written with a fraction or an exponent.
     */
    public function fractional(Numeral $number): bool
    {
        return !$number->hexadecimal && preg_match('/\A[0-9]+\z/', $number->text) !== 1;
    }
}
