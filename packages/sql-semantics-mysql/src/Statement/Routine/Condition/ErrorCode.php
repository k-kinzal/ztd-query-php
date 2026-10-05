<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Routine\Condition;

use SqlSemantics\Platform\MySql\Statement\Literal\Numeral;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Snapshot;

/**
 * A MySQL error code as a condition value.
 *
 * The grammar reads the code as an unsigned number (rule `ulong_num` of
 * sql_yacc.yy): the leading decimal digits of a decimal or floating number
 * (`1e2` is 1, `0.5` is 0), or the digits of a hexadecimal literal; a value
 * beyond the range of the conversion is its largest value. The code 0 is
 * rejected (ER_WRONG_VALUE), and the server keeps the code as a 32-bit
 * error number.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/declare-condition.html,
 * https://github.com/mysql/mysql-server/blob/mysql-8.0.44/sql/sql_yacc.yy (rules `sp_cond`, `ulong_num`).
 *
 * @visibility public
 * @example Holding an error code
 *     (new \SqlSemantics\Platform\MySql\Statement\Routine\Condition\ErrorCode(new \SqlSemantics\Platform\MySql\Statement\Literal\Numeral('1051')))->code->text // => '1051'
 * @example Reading the error number the server takes from a floating number
 *     (new \SqlSemantics\Platform\MySql\Statement\Routine\Condition\ErrorCode(new \SqlSemantics\Platform\MySql\Statement\Literal\Numeral('1051.9e3')))->number() // => 1051
 */
final class ErrorCode implements Condition
{
    use Snapshot;

    /**
     * @param Numeral $code The error code
     */
    public function __construct(public readonly Numeral $code)
    {
    }

    /**
     * Tells whether the code is not 0, the value the grammar rejects.
     */
    public function valid(): bool
    {
        return ltrim($this->digits(), '0') !== '';
    }

    /**
     * Answers the error number the server keeps: the value read by the grammar, truncated to 32 bits.
     */
    public function number(): int
    {
        $digits = ltrim($this->digits(), '0');
        $saturated = $this->code->hexadecimal
            ? strlen($digits) > 16 || (strlen($digits) === 16 && hexdec($digits[0]) >= 8)
            : strlen($digits) > 20 || (strlen($digits) === 20 && strcmp($digits, '18446744073709551615') > 0);
        if ($saturated) {
            return 0xFFFFFFFF;
        }
        if ($this->code->hexadecimal) {
            return (int) hexdec(substr(str_pad($digits, 8, '0', STR_PAD_LEFT), -8));
        }
        $number = 0;
        foreach (str_split($digits) as $digit) {
            $number = ($number * 10 + (int) $digit) % 0x100000000;
        }

        return $number;
    }

    /**
     * Answers the digits the grammar converts: all digits of a hexadecimal literal, the leading decimal digits otherwise.
     */
    public function digits(): string
    {
        return $this->code->hexadecimal ? $this->code->text : (string) preg_replace('/\D.*\z/s', '', $this->code->text);
    }

    /**
     * Writes the code.
     */
    public function render(Output $out): void
    {
        $out->node($this->code);
    }
}
