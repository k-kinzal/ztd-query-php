<?php

declare(strict_types=1);

namespace ZtdQuery\Exception;

use RuntimeException;

/**
 * Exception thrown when a FOREIGN KEY constraint is violated.
 */
final class ForeignKeyViolationException extends RuntimeException
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
     * The name of the foreign key constraint.
     */
    private string $constraintName;

    /**
     * The name of the referenced table.
     */
    private string $referencedTable;

    /**
     * The name of the referenced column.
     */
    private string $referencedColumn;

    /**
     * @param string $sql The SQL statement.
     * @param string $tableName The name of the table.
     * @param string $constraintName The name of the foreign key constraint.
     * @param string $referencedTable The name of the referenced table.
     * @param string $referencedColumn The name of the referenced column.
     */
    public function __construct(
        string $sql,
        string $tableName,
        string $constraintName,
        string $referencedTable,
        string $referencedColumn
    ) {
        parent::__construct(sprintf(
            "Foreign key constraint '%s' violated: referenced row not found in '%s.%s'.",
            $constraintName,
            $referencedTable,
            $referencedColumn
        ));
        $this->sql = $sql;
        $this->tableName = $tableName;
        $this->constraintName = $constraintName;
        $this->referencedTable = $referencedTable;
        $this->referencedColumn = $referencedColumn;
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
     * Get the name of the foreign key constraint.
     */
    public function getConstraintName(): string
    {
        return $this->constraintName;
    }

    /**
     * Get the name of the referenced table.
     */
    public function getReferencedTable(): string
    {
        return $this->referencedTable;
    }

    /**
     * Get the name of the referenced column.
     */
    public function getReferencedColumn(): string
    {
        return $this->referencedColumn;
    }
}
