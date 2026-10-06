<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Call\Temporal;

/**
 * The kind of value GET_FORMAT returns a format string for.
 *
 * Each case holds the keyword; TIMESTAMP and DATETIME give the same formats.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/date-and-time-functions.html#function_get-format.
 *
 * @visibility public
 * @example Reading the keyword of a case
 *     \SqlSemantics\Platform\MySql\Statement\Call\Temporal\TemporalFormat::DateTime->value // => 'DATETIME'
 */
enum TemporalFormat: string
{
    case Date = 'DATE';
    case Time = 'TIME';
    case Timestamp = 'TIMESTAMP';
    case DateTime = 'DATETIME';
}
