<?php

declare(strict_types=1);

namespace ZtdQuery\Shadow;

use ZtdQuery\Connection\StatementInterface;
use ZtdQuery\Exception\MissingPrimaryKeyException;

/**
 * Holds in-memory shadow rows for tables.
 *
 * @phpstan-import-type Row from StatementInterface
 */
class ShadowStore
{
    /**
     * Shadow rows keyed by table name.
     *
     * @var array<string, list<Row>>
     */
    private array $fixtures = [];

    /**
     * Tables explicitly initialized as fixtures or virtual schema entries.
     *
     * @var array<string, string>
     */
    private array $initializedTables = [];

    /**
     * Replaces every shadow row of a table.
     *
     * Rows are kept in order and under no keys of their own. Where the caller
     * has removed some and left the rest under the keys they had, the gaps go:
     * a table's rows are the rows it has, and nothing downstream should be able
     * to tell how they came to be that.
     *
     * @param array<int, Row> $rows Rows the table now has, in order
     */
    public function set(string $tableName, array $rows): void
    {
        $this->fixtures[$tableName] = array_values($rows);
        $this->initializedTables[$tableName] = $tableName;
    }

    /**
     * Get all shadow rows for a table.
     *
     * @return list<Row>
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
     * @return array<string, list<Row>>
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
     * @param list<Row> $rows
     */
    public function insert(string $tableName, array $rows): void
    {
        $current = $this->fixtures[$tableName] ?? [];
        $this->fixtures[$tableName] = array_merge($current, $rows);
    }

    /**
     * Delete rows from the shadow set.
     *
     * @param list<Row> $deletedRows
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
     * @param list<Row> $updatedRows
     * @param array<int, string> $primaryKeys
     *
     * @throws MissingPrimaryKeyException When the table declares no key to identify a row by
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
     * @param list<array{row: Row, identity: Row}> $updates
     * @param array<int, string> $primaryKeys
     *
     * @throws MissingPrimaryKeyException When the table declares no key to identify a row by
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
     * @param Row $left
     * @param Row $right
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
