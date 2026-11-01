<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Table\Catalog;

use SqlSemantics\Statement\Declaration\Table;
use SqlSemantics\Statement\Snapshot;

/**
 * One table of a system database: its columns, the declaration statements are bound against, and how INFORMATION_SCHEMA.TABLES lists it.
 *
 * @visibility public
 * @example Reading the type of a table of the mysql database
 *     \SqlSemantics\Platform\MySql\Statement\Table\Catalog\SystemTables::of(\SqlSemantics\Contract\GrammarRelease::MySql847)->find('mysql', 'user')?->type // => 'BASE TABLE'
 */
final class SystemTable
{
    use Snapshot;

    /**
     * @param string $schema The database: information_schema, mysql or performance_schema
     * @param string $name The table name
     * @param string $type The table type: SYSTEM VIEW or BASE TABLE
     * @param string|null $engine The storage engine, or null for a view
     * @param int|null $version The version of the table definition, or null
     * @param string|null $rowFormat The row format, or null
     * @param string|null $collation The default collation of the table, or null for a view
     * @param string $options The create options
     * @param string $comment The comment of the table
     * @param list<SystemColumn> $columns The columns in their order
     * @param Table $declaration The declaration statements are bound against
     */
    public function __construct(
        public readonly string $schema,
        public readonly string $name,
        public readonly string $type,
        public readonly ?string $engine,
        public readonly ?int $version,
        public readonly ?string $rowFormat,
        public readonly ?string $collation,
        public readonly string $options,
        public readonly string $comment,
        public readonly array $columns,
        public readonly Table $declaration,
    ) {
    }

    /**
     * Finds a column by name, without regard to case, or answers null.
     */
    public function column(string $name): ?SystemColumn
    {
        foreach ($this->columns as $column) {
            if (strcasecmp($column->name, $name) === 0) {
                return $column;
            }
        }

        return null;
    }
}
