<?php

declare(strict_types=1);

namespace MySqlMemory\Command\Admin;

use SqlSemantics\Platform\MySql\Statement\Literal\Numeral;
use SqlSemantics\Platform\MySql\Statement\Literal\Radix;
use SqlSemantics\Platform\MySql\Statement\Literal\Text;

/**
 * Reads the literals administrative statements take: the bytes of a string and the value of an unsigned number.
 *
 * A hexadecimal or bit literal stands for the bytes of its digits.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/hexadecimal-literals.html,
 * https://dev.mysql.com/doc/refman/8.4/en/bit-value-literals.html.
 *
 * @visibility MySqlMemory
 */
final class Literals
{
    /**
     * Answers the bytes a string literal stands for.
     *
     * @example A hexadecimal literal
     *     (new \MySqlMemory\Command\Admin\Literals())->bytes(new \SqlSemantics\Platform\MySql\Statement\Literal\Text('4142', \SqlSemantics\Platform\MySql\Statement\Literal\EscapeRule::Backslash, \SqlSemantics\Platform\MySql\Statement\Literal\Radix::Hexadecimal)) // => 'AB'
     */
    public function bytes(Text $text): string
    {
        $digits = $text->value;

        return match ($text->radix) {
            Radix::Hexadecimal => (string) hex2bin(strlen($digits) % 2 === 1 ? '0' . $digits : $digits),
            Radix::Bit => implode('', array_map(static fn (string $octet): string => chr((int) bindec($octet)), $digits === '' ? [] : str_split(str_pad($digits, (int) ceil(strlen($digits) / 8) * 8, '0', STR_PAD_LEFT), 8))),
            null => $digits,
        };
    }

    /**
     * Answers the value of an unsigned number as decimal digits, without leading zeros; the fraction of a decimal or floating number is cut.
     *
     * @return numeric-string
     *
     * @example A number with leading zeros
     *     (new \MySqlMemory\Command\Admin\Literals())->number(new \SqlSemantics\Platform\MySql\Statement\Literal\Numeral('000007208')) // => '7208'
     */
    public function number(Numeral $numeral): string
    {
        $text = $numeral->text;
        if ($numeral->hexadecimal) {
            $value = '0';
            foreach (str_split(strtolower($text === '' ? '0' : $text)) as $digit) {
                $value = bcadd(bcmul($value, '16'), (string) hexdec($digit));
            }

            return $value;
        }
        if (preg_match('/\A[0-9]+\z/', $text) === 1) {
            $trimmed = ltrim($text, '0');

            return is_numeric($trimmed) ? $trimmed : '0';
        }

        return number_format(floor((float) $text), 0, '.', '');
    }
}
