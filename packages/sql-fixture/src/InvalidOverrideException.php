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
}
