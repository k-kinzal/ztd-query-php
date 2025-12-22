<?php

declare(strict_types=1);

namespace SqlFixture\Schema;

use RuntimeException;

final class SchemaParseException extends RuntimeException
{
    public static function invalidSql(string $sql, string $reason): self
    {
        return new self(sprintf('Failed to parse SQL: %s. SQL: %s', $reason, $sql));
    }

    public static function notCreateTable(string $sql): self
    {
        return new self(sprintf('Expected CREATE TABLE statement, got: %s', $sql));
    }

    public static function noColumns(string $tableName): self
    {
        return new self(sprintf('No columns found in table: %s', $tableName));
    }
}
