<?php

declare(strict_types=1);

namespace MySqlMemory\Plan;

/**
 * The base column an output column reads directly, as the column definition of a result describes it.
 *
 * @visibility MySqlMemory
 */
final class ColumnOrigin
{
    /**
     * @param string $schema The database of the table
     * @param string $table The name the table is read through: its alias, or its name
     * @param string $originalTable The table name
     * @param string $column The column name as declared
     * @param int $flags The key and default flags of the column
     */
    public function __construct(public readonly string $schema, public readonly string $table, public readonly string $originalTable, public readonly string $column, public readonly int $flags = 0)
    {
    }

    /**
     * Answers the same origin without key flags, as a result buffered in a temporary table reports it.
     */
    public function unkeyed(): self
    {
        return new self($this->schema, $this->table, $this->originalTable, $this->column, $this->flags & ~(2 | 4 | 8));
    }

}
