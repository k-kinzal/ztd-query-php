<?php

declare(strict_types=1);

namespace ZtdQuery\Exception;

use RuntimeException;

/**
 * Exception thrown when attempting to add a column that already exists.
 */
final class ColumnAlreadyExistsException extends RuntimeException
{
    /**
     * The SQL statement that attempted to add the column.
     */
    private string $sql;

    /**
     * The name of the table.
     */
    private string $tableName;

    /**
     * The name of the column that already exists.
     */
    private string $columnName;

    /**
     * @param string $sql The SQL statement.
     * @param string $tableName The name of the table.
     * @param string $columnName The name of the column that already exists.
     */
    public function __construct(string $sql, string $tableName, string $columnName)
    {
        parent::__construct(sprintf("Column '%s' already exists in table '%s'.", $columnName, $tableName));
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
     * Get the name of the column that already exists.
     */
    public function getColumnName(): string
    {
        return $this->columnName;
    }
}
