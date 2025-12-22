<?php

declare(strict_types=1);

namespace ZtdQuery\Shadow\Mutation;

use ZtdQuery\Shadow\ShadowStore;

/**
 * Applies INSERT ... ON DUPLICATE KEY UPDATE (UPSERT) to the shadow store.
 */
final class UpsertMutation implements ShadowMutation
{
    /**
     * Target table to upsert into.
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
     * Columns to update on duplicate.
     *
     * @var array<int, string>
     */
    private array $updateColumns;

    /**
     * Values to use for update on duplicate.
     *
     * @var array<string, string>
     */
    private array $updateValues;

    /**
     * @param string $tableName Target table.
     * @param array<int, string> $primaryKeys Primary key columns.
     * @param array<int, string> $updateColumns Columns to update on duplicate.
     * @param array<string, string> $updateValues Values to use for update on duplicate.
     */
    public function __construct(string $tableName, array $primaryKeys, array $updateColumns = [], array $updateValues = [])
    {
        $this->tableName = $tableName;
        $this->primaryKeys = $primaryKeys;
        $this->updateColumns = $updateColumns;
        $this->updateValues = $updateValues;
    }

    /**
     * {@inheritDoc}
     */
    public function apply(ShadowStore $store, array $rows): void
    {
        $existingRows = $store->get($this->tableName);
        $insertRows = [];
        $updateRows = [];

        foreach ($rows as $row) {
            $existingIndex = $this->findDuplicateIndex($row, $existingRows);
            if ($existingIndex !== null) {
                // Update the existing row with values from ON DUPLICATE KEY UPDATE
                $updatedRow = $existingRows[$existingIndex];
                foreach ($this->updateColumns as $col) {
                    if (isset($this->updateValues[$col])) {
                        // Use the expression value from ON DUPLICATE KEY UPDATE
                        $value = $this->updateValues[$col];

                        // Handle VALUES(column) function - use value from INSERT row
                        if (preg_match('/^VALUES\s*\(\s*`?(\w+)`?\s*\)\s*$/i', $value, $m)) {
                            $refCol = $m[1];
                            $updatedRow[$col] = $row[$refCol] ?? $updatedRow[$col] ?? null;
                        } elseif (preg_match('/^[\'"](.*)[\'"]\s*$/s', $value, $m)) {
                            // Handle simple literal values (strip quotes)
                            $updatedRow[$col] = $m[1];
                        } else {
                            // Use the raw expression value
                            $updatedRow[$col] = $value;
                        }
                    } elseif (isset($row[$col])) {
                        $updatedRow[$col] = $row[$col];
                    }
                }
                // If no specific columns, update all columns except primary keys
                if ($this->updateColumns === []) {
                    foreach ($row as $col => $value) {
                        if (!in_array($col, $this->primaryKeys, true)) {
                            $updatedRow[$col] = $value;
                        }
                    }
                }
                $updateRows[$existingIndex] = $updatedRow;
            } else {
                $insertRows[] = $row;
            }
        }

        // Apply updates to existing rows
        foreach ($updateRows as $index => $updatedRow) {
            $existingRows[$index] = $updatedRow;
        }

        // Replace the table data and add new inserts
        $store->set($this->tableName, $existingRows);
        if ($insertRows !== []) {
            $store->insert($this->tableName, $insertRows);
        }
    }

    /**
     * {@inheritDoc}
     */
    public function tableName(): string
    {
        return $this->tableName;
    }

    /**
     * Find the index of a duplicate row based on primary keys.
     *
     * @param array<string, mixed> $row Row to check.
     * @param array<int, array<string, mixed>> $existingRows Existing rows in the store.
     * @return int|null Index of duplicate row, or null if no duplicate.
     */
    private function findDuplicateIndex(array $row, array $existingRows): ?int
    {
        foreach ($existingRows as $index => $existing) {
            $match = true;
            foreach ($this->primaryKeys as $key) {
                if (!isset($row[$key]) || !isset($existing[$key])) {
                    $match = false;
                    break;
                }
                if ($row[$key] != $existing[$key]) {
                    $match = false;
                    break;
                }
            }
            if ($match) {
                return $index;
            }
        }

        return null;
    }
}
