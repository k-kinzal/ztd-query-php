<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Type\Designation;

/**
 * The keyword that starts a date/time type with an optional time zone clause.
 *
 * Source: https://www.postgresql.org/docs/17/datatype-datetime.html.
 *
 * @visibility public
 * @example Spelling the time-of-day keyword
 *     \SqlSemantics\Platform\PostgreSql\Statement\Type\Designation\DatetimeKeyword::Time->value // => 'TIME'
 */
enum DatetimeKeyword: string
{
    case Timestamp = 'TIMESTAMP';
    case Time = 'TIME';
}
