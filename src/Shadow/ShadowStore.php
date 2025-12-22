<?php

declare(strict_types=1);

namespace ZtdQuery\Shadow;

/**
 * Holds in-memory shadow rows for tables.
 */
class ShadowStore
{
    /**
     * Shadow rows keyed by table name.
     *
     * @var array<string, array<int, array<string, mixed>>>
     */
    private array $fixtures = [];

    /**
     * Replace all shadow rows for a table.
     *
     * @param array<int, array<string, mixed>> $rows
     */
    public function set(string $tableName, array $rows): void
    {
        $this->fixtures[$tableName] = $rows;
    }

    /**
     * Get all shadow rows for a table.
     *
     * @return array<int, array<string, mixed>>
     */
    public function get(string $tableName): array
    {
        return $this->fixtures[$tableName] ?? [];
    }

    /**
     * Get all stored shadow tables.
     *
     * @return array<string, array<int, array<string, mixed>>>
     */
    public function getAll(): array
    {
        return $this->fixtures;
    }

    /**
     * Remove all shadow data.
     */
    public function clear(): void
    {
        $this->fixtures = [];
    }

    /**
     * Ensure a table key exists in the store.
     */
    public function ensure(string $tableName): void
    {
        if (!array_key_exists($tableName, $this->fixtures)) {
            $this->fixtures[$tableName] = [];
        }
    }

    /**
     * Append rows to a table shadow set.
     *
     * @param array<int, array<string, mixed>> $rows
     */
    public function insert(string $tableName, array $rows): void
    {
        $current = $this->fixtures[$tableName] ?? [];
        $this->fixtures[$tableName] = array_merge($current, $rows);
    }

    /**
     * Delete rows from the shadow set.
     *
     * @param array<int, array<string, mixed>> $deletedRows
     * @param array<int, string> $primaryKeys
     */
    public function delete(string $tableName, array $deletedRows, array $primaryKeys = []): void
    {
        if (!isset($this->fixtures[$tableName])) {
            return;
        }

        $currentRows = $this->fixtures[$tableName];
        $remainingRows = [];

        foreach ($currentRows as $currentRow) {
            $isDeleted = false;
            foreach ($deletedRows as $deletedRow) {
                if ($this->rowsMatch($currentRow, $deletedRow, $primaryKeys)) {
                    $isDeleted = true;
                    break;
                }
            }

            if (!$isDeleted) {
                $remainingRows[] = $currentRow;
            }
        }

        $this->fixtures[$tableName] = $remainingRows;
    }

    /**
     * Update rows matched by primary keys.
     *
     * @param array<int, array<string, mixed>> $updatedRows
     * @param array<int, string> $primaryKeys
     */
    public function update(string $tableName, array $updatedRows, array $primaryKeys): void
    {
        if (!isset($this->fixtures[$tableName])) {
            return;
        }

        if ($primaryKeys === []) {
            throw new \RuntimeException("UPDATE simulation requires primary keys for '$tableName'.");
        }

        $currentRows = &$this->fixtures[$tableName];

        foreach ($updatedRows as $updatedRow) {
            foreach ($currentRows as &$currentRow) {
                if ($this->rowsMatch($currentRow, $updatedRow, $primaryKeys)) {
                    $currentRow = $updatedRow;
                    break;
                }
            }
        }
    }

    /**
     * @param array<string, mixed> $left
     * @param array<string, mixed> $right
     * @param array<int, string> $primaryKeys
     */
    private function rowsMatch(array $left, array $right, array $primaryKeys): bool
    {
        if ($primaryKeys === []) {
            return $left == $right;
        }

        foreach ($primaryKeys as $key) {
            if (!array_key_exists($key, $left) || !array_key_exists($key, $right)) {
                return false;
            }
            if ($left[$key] != $right[$key]) {
                return false;
            }
        }

        return true;
    }
}
