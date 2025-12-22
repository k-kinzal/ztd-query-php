<?php

declare(strict_types=1);

namespace ZtdQuery\Exception;

use RuntimeException;

/**
 * Exception thrown when a referenced table does not exist.
 */
final class SchemaNotFoundException extends RuntimeException
{
    /**
     * The SQL statement that referenced the non-existent table.
     */
    private string $sql;

    /**
     * The name of the table that was not found.
     */
    private string $tableName;

    /**
     * @param string $sql The SQL statement.
     * @param string $tableName The name of the table that was not found.
     */
    public function __construct(string $sql, string $tableName)
    {
        parent::__construct(sprintf("Table '%s' does not exist.", $tableName));
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
     * Get the name of the table that was not found.
     */
    public function getTableName(): string
    {
        return $this->tableName;
    }
}
