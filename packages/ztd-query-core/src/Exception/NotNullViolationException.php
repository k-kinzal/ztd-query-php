<?php

declare(strict_types=1);

namespace ZtdQuery\Exception;

use RuntimeException;

/**
 * Exception thrown when a NOT NULL constraint is violated.
 */
final class NotNullViolationException extends RuntimeException
{
    /**
     * The SQL statement that caused the violation.
     */
    private string $sql;

    /**
     * The name of the table.
     */
    private string $tableName;

    /**
     * The name of the column that cannot be NULL.
     */
    private string $columnName;

    /**
     * @param string $sql The SQL statement.
     * @param string $tableName The name of the table.
     * @param string $columnName The name of the column that cannot be NULL.
     */
    public function __construct(string $sql, string $tableName, string $columnName)
    {
        parent::__construct(sprintf("Column '%s' in table '%s' cannot be NULL.", $columnName, $tableName));
        $this->sql = $sql;
        $this->tableName = $tableName;
        $this->columnName = $columnName;
    }

    /**
     * Get the SQL statement.
     */
    public function getSql(): string
    {
        return $this->sql;
    }

    /**
     * Get the name of the table.
     */
    public function getTableName(): string
    {
        return $this->tableName;
    }

    /**
     * Get the name of the column that cannot be NULL.
     */
    public function getColumnName(): string
    {
        return $this->columnName;
    }
}
