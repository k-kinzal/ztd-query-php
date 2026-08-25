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
    public static function unknownColumn(string $column, TableSchema $schema): self
    {
        return new self(sprintf(
            'Cannot override %s.%s: there is no such column. Its columns are: %s.',
            $schema->tableName,
            $column,
            implode(', ', $schema->getColumnNames())
        ));
    }

    public static function notNullable(string $column, TableSchema $schema): self
    {
        return new self(sprintf(
            'Cannot override %s.%s with null: the column is NOT NULL.',
            $schema->tableName,
            $column
        ));
    }

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
