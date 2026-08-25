<?php

declare(strict_types=1);

namespace SqlFixture\Schema;

/**
 * One table, as a fixture is generated against it.
 *
 * A table read from a declaration and a table read out of a live server arrive
 * here the same way, so everything downstream works against one description
 * rather than against a dialect.
 */
final class TableSchema
{
    /**
     * @param array<string, ColumnDefinition> $columns
     * @param list<string> $primaryKeys
     */
    public function __construct(
        public readonly string $tableName,
        public readonly array $columns,
        public readonly array $primaryKeys = [],
    ) {
    }

    /**
     * Answers one column of the table.
     *
     * @param string $name Column to answer for
     *
     * @return ColumnDefinition|null The column, or null when the table has no such column
     */
    public function getColumn(string $name): ?ColumnDefinition
    {
        return $this->columns[$name] ?? null;
    }

    /**
     * Reports whether the table has a column.
     *
     * @param string $name Column to answer for
     *
     * @return bool True when the table has it
     */
    public function hasColumn(string $name): bool
    {
        return isset($this->columns[$name]);
    }

    /**
     * @return list<string>
     */
    public function getColumnNames(): array
    {
        return array_keys($this->columns);
    }
}
