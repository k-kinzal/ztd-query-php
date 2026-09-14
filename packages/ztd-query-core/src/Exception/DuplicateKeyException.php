<?php

declare(strict_types=1);

namespace ZtdQuery\Exception;

use Stringable;

/**
 * Exception thrown when a PRIMARY KEY or UNIQUE constraint is violated.
 *
 * A refusal says what it is about without naming the schema it came from,
 * so the key values it carries are spelled out here rather than imported.
 *
 * @template TValue = mixed
 * @phpstan-type KeyValues array<string, TValue>
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
     * @var KeyValues
     */
    private array $keyValues;

    /**
     * @param string $sql The SQL statement.
     * @param string $tableName The name of the table.
     * @param string $keyName The name of the key constraint.
     * @param KeyValues $keyValues The duplicate key values.
     */
    public function __construct(string $sql, string $tableName, string $keyName, array $keyValues = [])
    {
        $renderedValues = [];
        foreach ($keyValues as $value) {
            $renderedValues[] = self::formatKeyValue($value);
        }
        $keyValuesStr = implode(', ', $renderedValues);
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
     * @return KeyValues
     */
    public function getKeyValues(): array
    {
        return $this->keyValues;
    }
    /**
     * Formats a duplicate value without coercing the value stored in the exception.
     */
    public static function formatKeyValue(mixed $value): string
    {
        if (is_string($value)) {
            return "'{$value}'";
        }
        if (is_scalar($value) || $value === null || is_resource($value) || $value instanceof Stringable) {
            return (string) $value;
        }

        return get_debug_type($value);
    }

}
