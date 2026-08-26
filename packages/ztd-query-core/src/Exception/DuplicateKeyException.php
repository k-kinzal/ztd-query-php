<?php

declare(strict_types=1);

namespace ZtdQuery\Exception;

use ZtdQuery\Connection\StatementInterface;

/**
 * Exception thrown when a PRIMARY KEY or UNIQUE constraint is violated.
 *
 * @phpstan-import-type Row from StatementInterface
 */
final class DuplicateKeyException extends SimulationException
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
     * The name of the key constraint that was violated.
     */
    private string $keyName;

    /**
     * The duplicate key values.
     *
     * @var Row
     */
    private array $keyValues;

    /**
     * @param string $sql The SQL statement.
     * @param string $tableName The name of the table.
     * @param string $keyName The name of the key constraint.
     * @param Row $keyValues The duplicate key values.
     */
    public function __construct(string $sql, string $tableName, string $keyName, array $keyValues = [])
    {
        $keyValuesStr = implode(', ', array_map(
            static fn (int|float|string|bool|null $value): string => is_string($value)
                ? "'{$value}'"
                : (string) $value,
            array_values($keyValues)
        ));
        parent::__construct(sprintf(
            "Duplicate entry '%s' for key '%s' in table '%s'.",
            $keyValuesStr,
            $keyName,
            $tableName
        ));
        $this->sql = $sql;
        $this->tableName = $tableName;
        $this->keyName = $keyName;
        $this->keyValues = $keyValues;
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
     * Get the name of the key constraint.
     */
    public function getKeyName(): string
    {
        return $this->keyName;
    }

    /**
     * Get the duplicate key values.
     *
     * @return Row
     */
    public function getKeyValues(): array
    {
        return $this->keyValues;
    }
}
