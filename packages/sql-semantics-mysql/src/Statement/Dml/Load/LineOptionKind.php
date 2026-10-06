<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Dml\Load;

/**
 * What a line option of a text file format sets: the line terminator or the line prefix.
 *
 * Each case holds the keyword it is written with before BY.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/load-data.html.
 *
 * @visibility public
 * @example Reading the keyword of a case
 *     \SqlSemantics\Platform\MySql\Statement\Dml\Load\LineOptionKind::Starting->value // => 'STARTING'
 */
enum LineOptionKind: string
{
    case Terminated = 'TERMINATED';
    case Starting = 'STARTING';
}
