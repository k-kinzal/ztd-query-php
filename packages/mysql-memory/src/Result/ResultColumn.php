<?php

declare(strict_types=1);

namespace MySqlMemory\Result;

/**
 * The definition of one column of a result set, as the server describes it to the client.
 *
 * @visibility public
 * @example Describing an integer column
 *     $column = new \MySqlMemory\Result\ResultColumn('id', \MySqlMemory\Result\FieldType::Long, 11, 0, \MySqlMemory\Result\ColumnFlag::NotNull->value, 63);
 *     [$column->name, $column->type->name, $column->binary()] // => ['id', 'Long', true]
 */
final class ResultColumn
{
    /**
     * @param string $name The column name the client sees, the alias when there is one
     * @param FieldType $type The type code
     * @param int $length The display length in bytes of the column's character set
     * @param int $decimals The number of decimals, 31 for a floating type of no fixed scale or a string
     * @param int $flags The ColumnFlag bits
     * @param int $charset The collation number of the column; 63 is binary
     * @param string $originalName The name of the base column, empty for an expression
     * @param string $table The table alias the column is read through
     * @param string $originalTable The name of the base table
     * @param string $schema The database of the base table
     */
    public function __construct(
        public readonly string $name,
        public readonly FieldType $type,
        public readonly int $length,
        public readonly int $decimals,
        public readonly int $flags,
        public readonly int $charset,
        public readonly string $originalName = '',
        public readonly string $table = '',
        public readonly string $originalTable = '',
        public readonly string $schema = '',
    ) {
    }

    /**
     * Tells whether the column holds bytes rather than characters, or a number.
     */
    public function binary(): bool
    {
        return $this->charset === 63;
    }

    /**
     * Tells whether the column is declared UNSIGNED.
     */
    public function unsigned(): bool
    {
        return ($this->flags & ColumnFlag::Unsigned->value) !== 0;
    }
}
