<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Configuration\Replication;

/**
 * Whether the SQL thread stops before the first or after the last transaction of a GTID set.
 * @visibility public
 * @example Reading the keyword
 *     \SqlSemantics\Model\Configuration\Replication\GtidUntil::Before->value // => 'SQL_BEFORE_GTIDS'
 */
enum GtidUntil: string
{
    case Before = 'SQL_BEFORE_GTIDS';
    case After = 'SQL_AFTER_GTIDS';
}
