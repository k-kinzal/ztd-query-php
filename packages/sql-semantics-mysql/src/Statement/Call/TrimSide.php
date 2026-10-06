<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Call;

/**
 * The side TRIM removes a string from, as written before FROM.
 *
 * Each case holds the keyword. No side means BOTH.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/string-functions.html#function_trim.
 *
 * @visibility public
 * @example Reading the keyword of a case
 *     \SqlSemantics\Platform\MySql\Statement\Call\TrimSide::Leading->value // => 'LEADING'
 */
enum TrimSide: string
{
    case Leading = 'LEADING';
    case Trailing = 'TRAILING';
    case Both = 'BOTH';
}
