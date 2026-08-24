<?php

declare(strict_types=1);

namespace SqlFixture;

use InvalidArgumentException;
use SqlFixture\Schema\TableSchema;

/**
 * An override that the table cannot accept.
 *
 * Overrides are the one route by which a generated row can stop matching the
 * schema it came from. The type mappers never produce null for a NOT NULL
 * column, or a value of the wrong shape; a caller can do both, and used to,
 * silently.
 */
final class InvalidOverrideException extends InvalidArgumentException
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
