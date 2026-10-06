<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Account\Option;

/**
 * The per-account resource limits of `WITH MAX_… n`.
 *
 * Mirrors USER_RESOURCES: queries, updates and connections per hour, and
 * simultaneous connections. A limit of 0 means no limit.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/create-user.html#create-user-resource-limits.
 *
 * @visibility public
 * @example Reading the keyword of a limit
 *     \SqlSemantics\Platform\MySql\Statement\Account\Option\ResourceKind::UserConnections->value // => 'MAX_USER_CONNECTIONS'
 */
enum ResourceKind: string
{
    case QueriesPerHour = 'MAX_QUERIES_PER_HOUR';
    case UpdatesPerHour = 'MAX_UPDATES_PER_HOUR';
    case ConnectionsPerHour = 'MAX_CONNECTIONS_PER_HOUR';
    case UserConnections = 'MAX_USER_CONNECTIONS';
}
