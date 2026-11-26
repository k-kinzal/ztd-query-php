<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Ir\Maintenance\Header;

/**
 * A read-only SQLite header or pager field.
 *
 * @visibility public
 * @example Observing the connection-local version of a database
 *     \SqlSemantics\Platform\Sqlite\Ir\Maintenance\Header\HeaderReadField::DataVersion->value // => 'data_version'
 */
enum HeaderReadField: string
{
    case FreeListCount = 'freelist_count';
    case DataVersion = 'data_version';
}
