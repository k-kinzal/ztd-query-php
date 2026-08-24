<?php

declare(strict_types=1);

namespace SqlFixture\Fixture;

use RuntimeException;
use SqlFixture\Plan\ColumnRef;
use SqlFixture\Schema\TableSchema;

/**
 * A plan that reads correctly but does not match the tables it names.
 */
final class PlanSchemaException extends RuntimeException
{
    public static function generatedColumn(ColumnRef $reference, string $column, TableSchema $schema): self
    {
        return new self(sprintf(
            'The plan links %s, but %s.%s is a generated column: the database computes '
            . 'it, so there is no value to carry across the relation and none to write '
            . 'into it. Link a stored column instead.',
            $reference->toString(),
            $schema->tableName,
            $column
        ));
    }

    public static function missingValue(string $childColumn, ColumnRef $parent, string $parentColumn): self
    {
        return new self(sprintf(
            'Cannot fill %s: the generated %s row has no %s to copy from.',
            $childColumn,
            $parent->table,
            $parentColumn
        ));
    }

    public static function unknownColumn(ColumnRef $reference, string $column, TableSchema $schema): self
    {
        return new self(sprintf(
            'The plan links %s, but %s has no column %s. Its columns are: %s.',
            $reference->toString(),
            $schema->tableName,
            $column,
            implode(', ', $schema->getColumnNames())
        ));
    }
}
