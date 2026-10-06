<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Replication\Source;

/**
 * The keyword settings of ASSIGN_GTIDS_TO_ANONYMOUS_TRANSACTIONS; the third setting is a UUID string.
 *
 * Each case holds the keyword it is written with: OFF assigns no GTIDs,
 * LOCAL assigns GTIDs with the replica's own UUID.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/change-replication-source-to.html#crs-opt-assign_gtids_to_anonymous_transactions.
 *
 * @visibility public
 * @example Reading the keyword of a setting
 *     \SqlSemantics\Platform\MySql\Statement\Replication\Source\AnonymousGtids::Local->value // => 'LOCAL'
 */
enum AnonymousGtids: string
{
    case Off = 'OFF';
    case Local = 'LOCAL';
}
