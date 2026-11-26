<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Ir\Maintenance\Metadata;

/**
 * SQLite metadata queries that select at most one table or view.
 *
 * @visibility public
 * @example Identifying the operation that includes generated columns
 *     \SqlSemantics\Platform\Sqlite\Ir\Maintenance\Metadata\TableInspectionKind::ExtendedColumns->value // => 'table_xinfo'
 */
enum TableInspectionKind: string
{
    case Columns = 'table_info';
    case ExtendedColumns = 'table_xinfo';
    case Indexes = 'index_list';
    case ForeignKeys = 'foreign_key_list';
}
