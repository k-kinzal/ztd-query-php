<?php

declare(strict_types=1);

namespace SqlFixture\Schema;

use RuntimeException;

/**
 * Schema parse exception.
 */
final class SchemaParseException extends RuntimeException
{
    /**
     * Returns invalid sql.
     */
    public static function invalidSql(string $sql, string $reason): self
    {
        return new self(sprintf('Failed to parse SQL: %s. SQL: %s', $reason, $sql));
    }

    /**
     * Returns not create table.
     */
    public static function notCreateTable(string $sql): self
    {
        return new self(sprintf('Expected CREATE TABLE statement, got: %s', $sql));
    }

    /**
     * Returns no columns.
     */
    public static function noColumns(string $tableName): self
    {
        return new self(sprintf('No columns found in table: %s', $tableName));
    }
}
