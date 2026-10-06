<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Account;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\MySql\Statement\Account\Problem\NumberOutOfRange;
use SqlSemantics\Platform\MySql\Statement\Literal\Numeral;

/**
 * Checks the numbers of account statements against the ranges the server's grammar actions enforce.
 *
 * Rule: MYSQL-ACCOUNT-NUMBER-001. A numeral is an integer when it is written
 * with digits only or as a hexadecimal literal; its value is computed
 * exactly in decimal digits, without a PHP integer or float. A decimal or
 * floating number at an integer position (real_ulong_num through
 * dec_num_error) is NumberOutOfRange with ER_ONLY_INTEGERS_ALLOWED; an
 * integer outside the range of its option is NumberOutOfRange with
 * ER_WRONG_VALUE. Precision: exact. Terminates: one pass over the digits.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/create-user.html#create-user-password-management,
 * https://dev.mysql.com/doc/refman/8.4/en/number-literals.html. Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class NumberChecks
{
    /**
     * Answers the decimal digits of an integer numeral without leading zeros, or null for a decimal or floating number.
     */
    public function value(Numeral $number): ?string
    {
        if (!$number->hexadecimal) {
            return preg_match('/\A[0-9]+\z/', $number->text) === 1 ? (ltrim($number->text, '0') === '' ? '0' : ltrim($number->text, '0')) : null;
        }
        $decimal = '0';
        foreach (str_split(strtolower($number->text)) as $digit) {
            $carry = (int) hexdec($digit);
            $product = '';
            for ($index = strlen($decimal) - 1; $index >= 0; $index--) {
                $step = ((int) $decimal[$index]) * 16 + $carry;
                $product = ($step % 10) . $product;
                $carry = intdiv($step, 10);
            }
            $decimal = ltrim(($carry > 0 ? (string) $carry : '') . $product, '0');
            $decimal = $decimal === '' ? '0' : $decimal;
        }

        return $decimal;
    }

    /**
     * Compares two decimal digit strings without leading zeros: negative, zero or positive.
     */
    public function compare(string $left, string $right): int
    {
        return strlen($left) === strlen($right) ? strcmp($left, $right) : strlen($left) - strlen($right);
    }

    /**
     * Reports a numeral that is no integer, or that lies outside the inclusive range of its option; a null maximum is no upper bound.
     */
    public function range(Derivation $derivation, Numeral $number, string $option, string $minimum, ?string $maximum): void
    {
        $value = $this->value($number);
        if ($value === null) {
            $derivation->report(new NumberOutOfRange($option, $number->text, 'ER_ONLY_INTEGERS_ALLOWED'));
        } elseif ($this->compare($value, $minimum) < 0 || ($maximum !== null && $this->compare($value, $maximum) > 0)) {
            $derivation->report(new NumberOutOfRange($option, $number->text, 'ER_WRONG_VALUE'));
        }
    }

    /**
     * Reports a factor number other than 2 and 3, which the server compares as written.
     */
    public function factor(Derivation $derivation, Numeral $factor): void
    {
        if ($factor->text !== '2' && $factor->text !== '3') {
            $derivation->report(new NumberOutOfRange('nth factor', $factor->text, 'ER_WRONG_VALUE'));
        }
    }
}
