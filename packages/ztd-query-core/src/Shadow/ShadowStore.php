<?php

declare(strict_types=1);

namespace ZtdQuery\Shadow;

use ZtdQuery\Exception\MissingPrimaryKeyException;

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
     * Tables explicitly initialized as fixtures or virtual schema entries.
     *
     * @var array<string, string>
     */
    private array $initializedTables = [];

    /**
     * Replace all shadow rows for a table.
     *
     * @param array<int, array<string, mixed>> $rows
     */
    public function set(string $tableName, array $rows): void
    {
        $this->fixtures[$tableName] = $rows;
        $this->initializedTables[$tableName] = $tableName;
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
     * Whether the store contains a shadow entry for a table, including an
     * intentionally empty table.
     */
    public function has(string $tableName): bool
    {
        return array_key_exists($tableName, $this->fixtures);
    }

    /**
     * Return the typed presence state for a shadow table.
     */
    public function state(string $tableName): ShadowTableState
    {
        if (!$this->has($tableName)) {
            return ShadowTableState::Missing;
        }

        if (isset($this->initializedTables[$tableName])) {
            return ShadowTableState::Initialized;
        }

        return ShadowTableState::Materialized;
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
        $this->initializedTables = [];
    }

    public function snapshot(): self
    {
        return clone $this;
    }

    public function restore(self $snapshot): void
    {
        $this->fixtures = $snapshot->fixtures;
        $this->initializedTables = $snapshot->initializedTables;
    }

    /**
     * Ensure a table key exists in the store.
     */
    public function ensure(string $tableName): void
    {
        if (!array_key_exists($tableName, $this->fixtures)) {
            $this->fixtures[$tableName] = [];
        }
        $this->initializedTables[$tableName] = $tableName;
    }

    /**
     * Remove both rows and explicit table context from the store.
     */
    public function remove(string $tableName): void
    {
        unset($this->fixtures[$tableName], $this->initializedTables[$tableName]);
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
            throw new MissingPrimaryKeyException($tableName);
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
     * @param list<array{row: array<string, mixed>, identity: array<string, mixed>}> $updates
     * @param array<int, string> $primaryKeys
     */
    public function updateIdentified(string $tableName, array $updates, array $primaryKeys): void
    {
        if (!isset($this->fixtures[$tableName])) {
            return;
        }
        if ($primaryKeys === []) {
            throw new MissingPrimaryKeyException($tableName);
        }

        $currentRows = &$this->fixtures[$tableName];
        foreach ($updates as $update) {
            foreach ($currentRows as &$currentRow) {
                if ($this->rowsMatch($currentRow, $update['identity'], $primaryKeys)) {
                    $currentRow = $update['row'];
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
            return $left === $right;
        }

        foreach ($primaryKeys as $key) {
            if (!array_key_exists($key, $left) || !array_key_exists($key, $right)) {
                return false;
            }
            if ($left[$key] !== $right[$key]) {
                return false;
            }
        }

        return true;
    }
}
