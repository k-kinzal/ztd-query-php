<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Table\Catalog;

use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Domain;
use SqlSemantics\Statement\Snapshot;

/**
 * One column of a system table: the type a read of it has, the metadata a result reports for it, and how INFORMATION_SCHEMA.COLUMNS lists it.
 *
 * The type and the metadata are those a result of the release reports for the column, which
 * for a view of INFORMATION_SCHEMA is the type of the expression the view computes, not always
 * the type INFORMATION_SCHEMA.COLUMNS lists.
 *
 * @visibility public
 * @example Reading a column of INFORMATION_SCHEMA.TABLES in 8.4
 *     $column = \SqlSemantics\Platform\MySql\Statement\Table\Catalog\SystemTables::of(\SqlSemantics\Contract\GrammarRelease::MySql847)->find('information_schema', 'tables')?->column('table_name');
 *     [$column?->name, $column?->domain->collation->name, $column?->nullable] // => ['TABLE_NAME', 'utf8mb3_bin', false]
 */
final class SystemColumn
{
    use Snapshot;

    /**
     * @param string $name The column name
     * @param Domain $domain The type a read of the column has
     * @param bool $nullable Whether the column can hold NULL
     * @param int $flags The column definition flags a result reports for the column
     * @param string $originalName The name of the column a result reports the column reads
     * @param string $originalTable The table a result reports the column is read from
     * @param string $database The database a result reports the column is read from; empty for a column a view computes
     * @param string $columnType The column type INFORMATION_SCHEMA.COLUMNS lists
     * @param string|null $default The default INFORMATION_SCHEMA.COLUMNS lists, or null
     * @param string $key The key INFORMATION_SCHEMA.COLUMNS lists: PRI, UNI, MUL or empty
     * @param string $extra The extra attributes INFORMATION_SCHEMA.COLUMNS lists
     * @param string $comment The comment of the column
     * @param string|null $collation The collation INFORMATION_SCHEMA.COLUMNS lists, or null for a column without one
     * @param bool $listedNullable Whether INFORMATION_SCHEMA.COLUMNS lists the column as nullable
     */
    public function __construct(
        public readonly string $name,
        public readonly Domain $domain,
        public readonly bool $nullable,
        public readonly int $flags,
        public readonly string $originalName,
        public readonly string $originalTable,
        public readonly string $database,
        public readonly string $columnType,
        public readonly ?string $default,
        public readonly string $key,
        public readonly string $extra,
        public readonly string $comment,
        public readonly ?string $collation,
        public readonly bool $listedNullable,
    ) {
    }
}
