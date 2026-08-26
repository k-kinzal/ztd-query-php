<?php

declare(strict_types=1);

namespace SqlFixture;

use RuntimeException;
use SqlFixture\Schema\TableSchema;

/**
 * Reports an override the table cannot hold.
 *
 * Refusing these is the point of the check rather than an accident of it: an
 * override naming a column that does not exist is silently dropped and the real
 * column generated at random, and a null bound to a NOT NULL column fails much
 * later at the insert. Saying so is declared behaviour, so it is reported at
 * runtime and a caller can catch it.
 */
final class InvalidOverrideException extends RuntimeException
{
    /**
     * Reports an override naming a column the table does not have.
     *
     * @param string $column Column the caller named
     * @param TableSchema $schema Table it was looked for on
     *
     * @return self Exception naming the column and what the table does have
     */
    public static function unknownColumn(string $column, TableSchema $schema): self
    {
        return new self(sprintf(
            'Cannot override %s.%s: there is no such column. Its columns are: %s.',
            $schema->tableName,
            $column,
            implode(', ', $schema->getColumnNames())
        ));
    }

    /**
     * Reports a null bound to a column that will not take one.
     *
     * @param string $column Column the caller set to null
     * @param TableSchema $schema Table it belongs to
     *
     * @return self Exception naming the column
     */
    public static function notNullable(string $column, TableSchema $schema): self
    {
        return new self(sprintf(
            'Cannot override %s.%s with null: the column is NOT NULL.',
            $schema->tableName,
            $column
        ));
    }

    /**
     * Reports an override on a column the server fills in.
     *
     * @param string $column Column the caller set
     * @param TableSchema $schema Table it belongs to
     *
     * @return self Exception naming the column
     */
    public static function generatedColumn(string $column, TableSchema $schema): self
    {
        return new self(sprintf(
            'Cannot override %s.%s: the database computes it, so a value written here '
            . 'would be rejected on insert.',
            $schema->tableName,
            $column
        ));
    }

    /**
     * Reports a row count no table could produce.
     *
     * @param string $table Table the count was written for
     * @param int $count Count as the caller wrote it
     *
     * @return self Exception naming the table and the count
     */
    public static function negativeRowCount(string $table, int $count): self
    {
        return new self(sprintf(
            'The row count for %s cannot be negative, got %d.',
            $table,
            $count
        ));
    }

    /**
     * Reports an override written as something no column could hold.
     *
     * @param array-key $column Column the value was written for
     * @param string $type Type of the value, as PHP names it
     *
     * @return self Exception naming the column and what was written there
     */
    public static function unsupportedValue(int|string $column, string $type): self
    {
        return new self(sprintf(
            'The override for "%s" must be a scalar, null, or an array of those, got %s.',
            (string) $column,
            $type
        ));
    }

    /**
     * Reports an override holding something a column cannot carry.
     *
     * A JSON column may be written as an array of scalars, so an array is read
     * one level deep. What is nested below that could never be bound, whatever
     * the column.
     *
     * @param array-key $column Column the value was written for
     * @param string $type Type of the nested value, as PHP names it
     *
     * @return self Exception naming the column and what it was found holding
     */
    public static function nestedValue(int|string $column, string $type): self
    {
        return new self(sprintf(
            'The override for "%s" holds a %s, which no column can carry.',
            (string) $column,
            $type
        ));
    }
}
