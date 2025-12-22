<?php

declare(strict_types=1);

namespace ZtdQuery\Shadow\Mutation;

use ZtdQuery\Exception\DuplicateKeyException;
use ZtdQuery\Exception\NotNullViolationException;
use ZtdQuery\Schema\TableDefinition;
use ZtdQuery\Shadow\ShadowStore;

/**
 * Applies UPDATE result rows to the shadow store.
 */
final class UpdateMutation implements ShadowMutation
{
    /**
     * Target table to update.
     *
     * @var string
     */
    private string $tableName;

    /**
     * Primary key columns used to match rows.
     *
     * @var array<int, string>
     */
    private array $primaryKeys;

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
     * @param TableDefinition|null $tableDefinition Table definition for constraint validation.
     * @param string $sql Original SQL statement for exception messages.
     * @param bool $validateConstraints Whether to validate constraints.
     */
    public function __construct(
        string $tableName,
        array $primaryKeys,
        ?TableDefinition $tableDefinition = null,
        string $sql = '',
        bool $validateConstraints = false
    ) {
        $this->tableName = $tableName;
        $this->primaryKeys = $primaryKeys;
        $this->tableDefinition = $tableDefinition;
        $this->sql = $sql;
        $this->validateConstraints = $validateConstraints;
    }

    /**
     * {@inheritDoc}
     */
    public function apply(ShadowStore $store, array $rows): void
    {
        if ($this->validateConstraints && $this->tableDefinition !== null) {
            $existingRows = $store->get($this->tableName);

            foreach ($rows as $row) {
                $this->validateNotNullConstraints($row);
                $this->validateUniqueConstraints($row, $existingRows);
            }
        }

        $store->update($this->tableName, $rows, $this->primaryKeys);
    }

    /**
     * {@inheritDoc}
     */
    public function tableName(): string
    {
        return $this->tableName;
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
                if ($this->isSameRowByPrimaryKey($row, $existing)) {
                    continue;
                }

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
     * Check if two rows are the same based on primary key.
     *
     * @param array<string, mixed> $row1 First row.
     * @param array<string, mixed> $row2 Second row.
     * @return bool True if same row.
     */
    private function isSameRowByPrimaryKey(array $row1, array $row2): bool
    {
        if ($this->primaryKeys === []) {
            return false;
        }

        foreach ($this->primaryKeys as $key) {
            if (!isset($row1[$key]) || !isset($row2[$key])) {
                return false;
            }
            if ($row1[$key] !== $row2[$key]) {
                return false;
            }
        }

        return true;
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
