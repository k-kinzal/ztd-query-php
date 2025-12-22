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
                $updatedRow = $existingRows[$existingIndex];
                foreach ($this->updateColumns as $col) {
                    if (isset($this->updateValues[$col])) {
                        $value = $this->updateValues[$col];

                        if (preg_match('/^VALUES\s*\(\s*`?(\w+)`?\s*\)\s*$/i', $value, $m) === 1) {
                            $refCol = $m[1];
                            $updatedRow[$col] = $row[$refCol] ?? $updatedRow[$col] ?? null;
                        } elseif (preg_match('/^EXCLUDED\."?(\w+)"?\s*$/i', $value, $m) === 1) {
                            $refCol = $m[1];
                            $updatedRow[$col] = $row[$refCol] ?? $updatedRow[$col] ?? null;
                        } elseif (preg_match('/^[\'"](.*)[\'"]\s*$/s', $value, $m) === 1) {
                            $updatedRow[$col] = $m[1];
                        } else {
                            $updatedRow[$col] = $value;
                        }
                    } elseif (isset($row[$col])) {
                        $updatedRow[$col] = $row[$col];
                    }
                }
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

        foreach ($updateRows as $index => $updatedRow) {
            $existingRows[$index] = $updatedRow;
        }

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
                if ($row[$key] !== $existing[$key]) {
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
