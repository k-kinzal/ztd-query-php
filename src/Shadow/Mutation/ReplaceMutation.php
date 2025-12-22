<?php

declare(strict_types=1);

namespace ZtdQuery\Shadow\Mutation;

use ZtdQuery\Shadow\ShadowStore;

/**
 * Applies REPLACE INTO operation to the shadow store.
 * REPLACE deletes the existing row and inserts the new one.
 */
final class ReplaceMutation implements ShadowMutation
{
    /**
     * Target table to replace into.
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
     * @param string $tableName Target table.
     * @param array<int, string> $primaryKeys Primary key columns.
     */
    public function __construct(string $tableName, array $primaryKeys = [])
    {
        $this->tableName = $tableName;
        $this->primaryKeys = $primaryKeys;
    }

    /**
     * {@inheritDoc}
     */
    public function apply(ShadowStore $store, array $rows): void
    {
        $existingRows = $store->get($this->tableName);

        foreach ($rows as $row) {
            // Find and remove duplicate row
            $existingRows = array_filter($existingRows, function ($existing) use ($row) {
                return !$this->rowsMatch($existing, $row);
            });
        }

        // Re-index array and add new rows
        $existingRows = array_values($existingRows);
        $store->set($this->tableName, $existingRows);
        $store->insert($this->tableName, $rows);
    }

    /**
     * {@inheritDoc}
     */
    public function tableName(): string
    {
        return $this->tableName;
    }

    /**
     * Check if rows match based on primary keys.
     *
     * @param array<string, mixed> $existing Existing row.
     * @param array<string, mixed> $new New row.
     * @return bool True if rows match on primary keys.
     */
    private function rowsMatch(array $existing, array $new): bool
    {
        if ($this->primaryKeys === []) {
            // Without primary keys, compare all columns
            return $existing == $new;
        }

        foreach ($this->primaryKeys as $key) {
            if (!isset($existing[$key]) || !isset($new[$key])) {
                return false;
            }
            if ($existing[$key] != $new[$key]) {
                return false;
            }
        }

        return true;
    }
}
