<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Maintenance;

/**
 * Selects user-table indexes or system-table indexes in the current database.
 * @visibility public
 * @example Choosing the reindex target
 *     \SqlSemantics\Model\Maintenance\DatabaseIndexScope::UserTables->value // => 'DATABASE'
 */
enum DatabaseIndexScope: string
{
    case UserTables = 'DATABASE';
    case SystemTables = 'SYSTEM';
}
