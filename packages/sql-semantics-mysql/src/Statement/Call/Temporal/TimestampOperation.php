<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Call\Temporal;

/**
 * The two functions that take a unit before two operands: TIMESTAMPADD and TIMESTAMPDIFF.
 *
 * Each case holds the keyword.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/date-and-time-functions.html#function_timestampadd.
 *
 * @visibility public
 * @example Reading the keyword of a case
 *     \SqlSemantics\Platform\MySql\Statement\Call\Temporal\TimestampOperation::Difference->value // => 'TIMESTAMPDIFF'
 */
enum TimestampOperation: string
{
    case Add = 'TIMESTAMPADD';
    case Difference = 'TIMESTAMPDIFF';
}
