<?php

declare(strict_types=1);

namespace ZtdQuery\Schema;

/**
 * Structured representation of a table's schema metadata.
 */
final class TableDefinition
{
    /**
     * @var array<string, ColumnType>
     */
    public readonly array $typedColumns;

    /**
     * @param array<int, string> $columns Column names in declaration order.
     * @param array<string, string> $columnTypes Column name => MySQL type string.
     * @param array<int, string> $primaryKeys Primary key column names.
     * @param array<int, string> $notNullColumns Columns with NOT NULL constraint.
     * @param array<string, array<int, string>> $uniqueConstraints Key name => column list.
     * @param array<string, ColumnType> $typedColumns Column name => structured ColumnType.
     */
    public function __construct(
        public readonly array $columns,
        public readonly array $columnTypes,
        public readonly array $primaryKeys,
        public readonly array $notNullColumns,
        public readonly array $uniqueConstraints,
        array $typedColumns = [],
    ) {
        $this->typedColumns = $typedColumns;
    }
}
