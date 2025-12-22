<?php

declare(strict_types=1);

namespace ZtdQuery\Shadow\Mutation;

use ZtdQuery\Exception\DuplicateKeyException;
use ZtdQuery\Exception\NotNullViolationException;
use ZtdQuery\Schema\TableDefinition;
use ZtdQuery\Shadow\ShadowStore;

/**
 * Applies INSERT result rows to the shadow store.
 */
final class InsertMutation implements ShadowMutation
{
    /**
     * Target table to insert into.
     *
     * @var string
     */
    private string $tableName;

    /**
     * Primary key columns for duplicate detection.
     *
     * @var array<int, string>
     */
    private array $primaryKeys;

    /**
     * Whether to ignore duplicate key errors.
     *
     * @var bool
     */
    private bool $ignore;

    /**
     * Table definition for constraint validation.
     *
     * @var TableDefinition|null
     */
    private ?TableDefinition $tableDefinition;

    /**
     * Original SQL statement for exception messages.
     *
     * @var string
     */
    private string $sql;

    /**
     * Whether constraint validation is enabled.
     *
     * @var bool
     */
    private bool $validateConstraints;

    /**
     * @param string $tableName Target table.
     * @param array<int, string> $primaryKeys Primary key columns.
     * @param bool $ignore Whether to ignore duplicates (INSERT IGNORE).
     * @param TableDefinition|null $tableDefinition Table definition for constraint validation.
     * @param string $sql Original SQL statement for exception messages.
     * @param bool $validateConstraints Whether to validate constraints.
     */
    public function __construct(
        string $tableName,
        array $primaryKeys = [],
        bool $ignore = false,
        ?TableDefinition $tableDefinition = null,
        string $sql = '',
        bool $validateConstraints = false
    ) {
        $this->tableName = $tableName;
        $this->primaryKeys = $primaryKeys;
        $this->ignore = $ignore;
        $this->tableDefinition = $tableDefinition;
        $this->sql = $sql;
        $this->validateConstraints = $validateConstraints;
    }

    /**
     * {@inheritDoc}
     */
    public function apply(ShadowStore $store, array $rows): void
    {
        $existingRows = $store->get($this->tableName);
        $filteredRows = [];

        foreach ($rows as $row) {
            if ($this->validateConstraints && $this->tableDefinition !== null) {
                $this->validateNotNullConstraints($row);
            }

            if ($this->primaryKeys !== []) {
                $isDuplicate = $this->isDuplicate($row, $existingRows);

                if ($isDuplicate) {
                    if ($this->ignore) {
                        continue;
                    }

                    if ($this->validateConstraints) {
                        $keyValues = $this->extractKeyValues($row, $this->primaryKeys);
                        throw new DuplicateKeyException(
                            $this->sql,
                            $this->tableName,
                            'PRIMARY',
                            $keyValues
                        );
                    }
                }
            }

            if ($this->validateConstraints && $this->tableDefinition !== null) {
                $this->validateUniqueConstraints($row, $existingRows);
            }

            $filteredRows[] = $row;
            $existingRows[] = $row;
        }

        $store->insert($this->tableName, $filteredRows);
    }

    /**
     * {@inheritDoc}
     */
    public function tableName(): string
    {
        return $this->tableName;
    }

    /**
     * Check if a row would be a duplicate based on primary keys.
     *
     * @param array<string, mixed> $row Row to check.
     * @param array<int, array<string, mixed>> $existingRows Existing rows in the store.
     * @return bool True if duplicate.
     */
    private function isDuplicate(array $row, array $existingRows): bool
    {
        foreach ($existingRows as $existing) {
            $match = true;
            foreach ($this->primaryKeys as $key) {
                if (!isset($row[$key]) || !isset($existing[$key])) {
                    $match = false;
                    break;
                }
                if ($row[$key] !== $existing[$key]) {
                    $match = false;
                    break;
                }
            }
            if ($match) {
                return true;
            }
        }

        return false;
    }

    /**
     * Validate NOT NULL constraints for a row.
     *
     * @param array<string, mixed> $row Row to validate.
     * @throws NotNullViolationException If a NOT NULL constraint is violated.
     */
    private function validateNotNullConstraints(array $row): void
    {
        if ($this->tableDefinition === null) {
            return;
        }

        $notNullColumns = $this->tableDefinition->notNullColumns;

        foreach ($notNullColumns as $columnName) {
            if (array_key_exists($columnName, $row) && $row[$columnName] === null) {
                throw new NotNullViolationException($this->sql, $this->tableName, $columnName);
            }
        }
    }

    /**
     * Validate UNIQUE constraints for a row.
     *
     * @param array<string, mixed> $row Row to validate.
     * @param array<int, array<string, mixed>> $existingRows Existing rows in the store.
     * @throws DuplicateKeyException If a UNIQUE constraint is violated.
     */
    private function validateUniqueConstraints(array $row, array $existingRows): void
    {
        if ($this->tableDefinition === null) {
            return;
        }

        $uniqueConstraints = $this->tableDefinition->uniqueConstraints;

        foreach ($uniqueConstraints as $keyName => $columns) {
            $hasNull = false;
            foreach ($columns as $col) {
                if (!array_key_exists($col, $row) || $row[$col] === null) {
                    $hasNull = true;
                    break;
                }
            }
            if ($hasNull) {
                continue;
            }

            foreach ($existingRows as $existing) {
                $match = true;
                foreach ($columns as $col) {
                    if (!isset($existing[$col]) || $row[$col] !== $existing[$col]) {
                        $match = false;
                        break;
                    }
                }
                if ($match) {
                    $keyValues = $this->extractKeyValues($row, $columns);
                    throw new DuplicateKeyException($this->sql, $this->tableName, $keyName, $keyValues);
                }
            }
        }
    }

    /**
     * Extract key values from a row.
     *
     * @param array<string, mixed> $row Row to extract from.
     * @param array<int, string> $columns Column names.
     * @return array<string, mixed> Key values.
     */
    private function extractKeyValues(array $row, array $columns): array
    {
        $values = [];
        foreach ($columns as $col) {
            $values[$col] = $row[$col] ?? null;
        }
        return $values;
    }
}
