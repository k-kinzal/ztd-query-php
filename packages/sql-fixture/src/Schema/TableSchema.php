<?php

declare(strict_types=1);

namespace SqlFixture\Schema;

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

    public function getColumn(string $name): ?ColumnDefinition
    {
        return $this->columns[$name] ?? null;
    }

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
