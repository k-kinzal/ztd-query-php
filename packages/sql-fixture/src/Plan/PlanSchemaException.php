<?php

declare(strict_types=1);

namespace SqlFixture\Plan;

use RuntimeException;
use SqlFixture\Schema\TableSchema;

/**
 * A plan that reads correctly but does not match the tables it names.
 */
final class PlanSchemaException extends RuntimeException
{
    /**
     * Reports a plan binding a column the server fills in.
     *
     * A generated column takes whatever the server computes, so a value bound
     * to it by a relation would be thrown away and the rows on either end would
     * no longer agree.
     *
     * @param ColumnRef $reference Endpoint that named the column
     * @param string $column Column that is generated
     * @param TableSchema $schema Table it belongs to
     *
     * @return self Exception naming the column and what the table does have
     */
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

    /**
     * Reports a parent row that does not carry a column the relation reads.
     *
     * @param string $childColumn Column on the child that needed a value
     * @param ColumnRef $parent Endpoint on the parent
     * @param string $parentColumn Column the value was to be read from
     *
     * @return self Exception naming both columns
     */
    public static function missingValue(string $childColumn, ColumnRef $parent, string $parentColumn): self
    {
        return new self(sprintf(
            'Cannot fill %s: the generated %s row has no %s to copy from.',
            $childColumn,
            $parent->table,
            $parentColumn
        ));
    }

    /**
     * Reports a plan naming a column the table does not have.
     *
     * @param ColumnRef $reference Endpoint that named the column
     * @param string $column Column that does not exist
     * @param TableSchema $schema Table it was looked for on
     *
     * @return self Exception naming the column and what the table does have
     */
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
