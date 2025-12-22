<?php

declare(strict_types=1);

namespace ZtdQuery\Exception;

use RuntimeException;

/**
 * Exception thrown when a referenced column does not exist.
 */
final class ColumnNotFoundException extends RuntimeException
{
    /**
     * The SQL statement that referenced the non-existent column.
     */
    private string $sql;

    /**
     * The name of the table.
     */
    private string $tableName;

    /**
     * The name of the column that was not found.
     */
    private string $columnName;

    /**
     * @param string $sql The SQL statement.
     * @param string $tableName The name of the table.
     * @param string $columnName The name of the column that was not found.
     */
    public function __construct(string $sql, string $tableName, string $columnName)
    {
        parent::__construct(sprintf("Column '%s' does not exist in table '%s'.", $columnName, $tableName));
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
     * Get the name of the column that was not found.
     */
    public function getColumnName(): string
    {
        return $this->columnName;
    }
}
