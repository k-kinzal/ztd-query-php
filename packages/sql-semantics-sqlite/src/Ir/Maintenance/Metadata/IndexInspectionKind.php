<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Ir\Maintenance\Metadata;

/**
 * The native SQLite index metadata views.
 *
 * @visibility public
 * @example Including auxiliary index entries
 *     \SqlSemantics\Platform\Sqlite\Ir\Maintenance\Metadata\IndexInspectionKind::Extended->value // => 'index_xinfo'
 */
enum IndexInspectionKind: string
{
    case Basic = 'index_info';
    case Extended = 'index_xinfo';
}
