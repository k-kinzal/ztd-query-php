<?php

declare(strict_types=1);

namespace SqlFixture\Schema;

use RuntimeException;

/**
 * Reports a declaration that could not be read as a table.
 */
final class SchemaParseException extends RuntimeException
{
    /**
     * Reports a statement that could not be read.
     *
     * @param string $sql Statement as it was written
     * @param string $reason What stopped it being read
     *
     * @return self Exception carrying both
     */
    public static function invalidSql(string $sql, string $reason): self
    {
        return new self(sprintf('Failed to parse SQL: %s. SQL: %s', $reason, $sql));
    }

    /**
     * Reports a statement that is not a CREATE TABLE.
     *
     * @param string $sql Statement as it was written
     *
     * @return self Exception quoting the statement
     */
    public static function notCreateTable(string $sql): self
    {
        return new self(sprintf('Expected CREATE TABLE statement, got: %s', $sql));
    }

    /**
     * Reports a table declared without any columns.
     *
     * @param string $tableName Table the statement declares
     *
     * @return self Exception naming the table
     */
    public static function noColumns(string $tableName): self
    {
        return new self(sprintf('No columns found in table: %s', $tableName));
    }
}
