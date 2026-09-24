<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Account\Policy;

/**
 * Per-account server resource limits; a zero value removes the limit.
 * @visibility public
 * @example Inspecting a resource limit kind
 *     \SqlSemantics\Model\Definition\Account\Policy\ResourceLimitKind::QueriesPerHour->value // => 'MAX_QUERIES_PER_HOUR'
 */
enum ResourceLimitKind: string
{
    case QueriesPerHour = 'MAX_QUERIES_PER_HOUR';
    case UpdatesPerHour = 'MAX_UPDATES_PER_HOUR';
    case ConnectionsPerHour = 'MAX_CONNECTIONS_PER_HOUR';
    case UserConnections = 'MAX_USER_CONNECTIONS';
}
