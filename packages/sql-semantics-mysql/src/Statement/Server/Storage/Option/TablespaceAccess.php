<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Server\Storage\Option;

/**
 * The access mode ALTER TABLESPACE sets in MySQL 5.x: READ_ONLY, READ_WRITE or NOT ACCESSIBLE.
 *
 * Mirrors ts_access_mode. Each case holds the keywords it is written with.
 * Source: https://dev.mysql.com/doc/refman/5.7/en/alter-tablespace.html.
 *
 * @visibility public
 * @example Reading the keywords of a case
 *     \SqlSemantics\Platform\MySql\Statement\Server\Storage\Option\TablespaceAccess::NotAccessible->value // => 'NOT ACCESSIBLE'
 */
enum TablespaceAccess: string
{
    case ReadOnly = 'READ_ONLY';
    case ReadWrite = 'READ_WRITE';
    case NotAccessible = 'NOT ACCESSIBLE';
}
