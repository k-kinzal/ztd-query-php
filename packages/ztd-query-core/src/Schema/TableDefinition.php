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

    /** @var array<string, string> */
    public readonly array $columnDefaults;

    /** @var array<string, IdentityGenerationStrategy> */
    public readonly array $identityStrategies;

    /**
     * @param array<int, string> $columns Column names in declaration order.
     * @param array<string, string> $columnTypes Column name => MySQL type string.
     * @param array<int, string> $primaryKeys Primary key column names.
     * @param array<int, string> $notNullColumns Columns with NOT NULL constraint.
     * @param array<string, array<int, string>> $uniqueConstraints Key name => column list.
     * @param array<string, ColumnType> $typedColumns Column name => structured ColumnType.
     * @param array<string, string> $columnDefaults Column name => SQL default expression.
     * @param array<string, IdentityGenerationStrategy> $identityStrategies Column name => shadow generation strategy.
     */
    public function __construct(
        public readonly array $columns,
        public readonly array $columnTypes,
        public readonly array $primaryKeys,
        public readonly array $notNullColumns,
        public readonly array $uniqueConstraints,
        array $typedColumns = [],
        array $columnDefaults = [],
        array $identityStrategies = [],
    ) {
        $this->typedColumns = $typedColumns;
        $this->columnDefaults = $columnDefaults;
        $this->identityStrategies = $identityStrategies;
    }
}
