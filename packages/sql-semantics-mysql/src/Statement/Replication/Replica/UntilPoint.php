<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Replication\Replica;

/**
 * The UNTIL conditions of START REPLICA that are not a log position: a GTID set, or the end of the gaps of a multithreaded applier.
 *
 * Each case holds the keyword it is written with.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/start-replica.html.
 *
 * @visibility public
 * @example Reading the keyword of a condition
 *     \SqlSemantics\Platform\MySql\Statement\Replication\Replica\UntilPoint::BeforeGtids->value // => 'SQL_BEFORE_GTIDS'
 */
enum UntilPoint: string
{
    case BeforeGtids = 'SQL_BEFORE_GTIDS';
    case AfterGtids = 'SQL_AFTER_GTIDS';
    case AfterGaps = 'SQL_AFTER_MTS_GAPS';
}
