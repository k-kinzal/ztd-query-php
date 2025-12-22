<?php

declare(strict_types=1);

namespace ZtdQuery\Exception;

use RuntimeException;

/**
 * Exception thrown when attempting to create a table that already exists.
 */
final class TableAlreadyExistsException extends RuntimeException
{
    /**
     * The SQL statement that attempted to create the table.
     */
    private string $sql;

    /**
     * The name of the table that already exists.
     */
    private string $tableName;

    /**
     * @param string $sql The SQL statement.
     * @param string $tableName The name of the table that already exists.
     */
    public function __construct(string $sql, string $tableName)
    {
        parent::__construct(sprintf("Table '%s' already exists.", $tableName));
        $this->sql = $sql;
        $this->tableName = $tableName;
    }

    /**
     * Get the SQL statement.
     */
    public function getSql(): string
    {
        return $this->sql;
    }

    /**
     * Get the name of the table that already exists.
     */
    public function getTableName(): string
    {
        return $this->tableName;
    }
}
