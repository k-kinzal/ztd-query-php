<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Type\Kind;

/**
 * The date and time types of MySQL.
 *
 * Each case holds the keywords the type is written with.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/date-and-time-types.html.
 *
 * @visibility public
 * @example Reading the keywords of a case
 *     \SqlSemantics\Platform\MySql\Statement\Type\Kind\TemporalKind::DateTime->value // => 'DATETIME'
 */
enum TemporalKind: string
{
    case Date = 'DATE';
    case Time = 'TIME';
    case Timestamp = 'TIMESTAMP';
    case DateTime = 'DATETIME';
    case Year = 'YEAR';
}
