<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Noise;

/**
 * The noise and synonym token positions of the productions of function-like expressions.
 *
 * Only a token with no influence on meaning in its production may be listed
 * as noise, and only terminals the manual defines as synonyms may share a
 * key. Every entry is listed in the method documentation with its reason and
 * the manual page that states it. Nothing else is skipped or merged by the
 * token correspondence check.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class CallNoise
{
    /**
     * Answers the noise positions by production signature.
     *
     * - `func_datetime_precision: ( )`: CURTIME(), NOW(), SYSDATE(),
     *   UTC_TIME() and UTC_TIMESTAMP() without a precision are the same
     *   functions as the forms without parentheses, as CURRENT_TIME and
     *   CURRENT_TIMESTAMP are; the parentheses carry no operand
     *   (https://dev.mysql.com/doc/refman/8.4/en/date-and-time-functions.html#function_now:
     *   "CURRENT_TIMESTAMP and CURRENT_TIMESTAMP() are synonyms for NOW()").
     *
     * @return array<string, list<int>>
     */
    public static function positions(): array
    {
        return [
            'func_datetime_precision: ( )' => [0, 1],
        ];
    }

    /**
     * Answers the key of each synonym position by production signature.
     *
     * - `SUBSTRING(str FROM pos FOR len)` is `SUBSTRING(str, pos, len)`: FROM
     *   and FOR take the keys of the commas
     *   (https://dev.mysql.com/doc/refman/8.4/en/string-functions.html#function_substring:
     *   "The forms with a FROM keyword are standard SQL syntax";
     *   the server builds the same Item_func_substr for both).
     * - `ADDDATE(date, INTERVAL expr unit)` is `DATE_ADD(date, INTERVAL expr
     *   unit)` and `SUBDATE(...)` with INTERVAL is `DATE_SUB(...)`
     *   (https://dev.mysql.com/doc/refman/8.4/en/date-and-time-functions.html#function_adddate:
     *   "When invoked with the INTERVAL form of the second argument, ADDDATE()
     *   is a synonym for DATE_ADD()"; likewise SUBDATE() for DATE_SUB()).
     *
     * @return array<string, array<int, string>>
     */
    public static function synonyms(): array
    {
        return [
            'function_call_nonkeyword: SUBSTRING ( expr FROM expr FOR_SYM expr )' => [3 => ',', 5 => ','],
            'function_call_nonkeyword: SUBSTRING ( expr FROM expr )' => [3 => ','],
            'function_call_nonkeyword: ADDDATE_SYM ( expr , INTERVAL_SYM expr interval )' => [0 => 'DATE_ADD_INTERVAL'],
            'function_call_nonkeyword: SUBDATE_SYM ( expr , INTERVAL_SYM expr interval )' => [0 => 'DATE_SUB_INTERVAL'],
        ];
    }
}
