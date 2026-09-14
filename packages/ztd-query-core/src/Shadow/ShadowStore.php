<?php

declare(strict_types=1);

namespace ZtdQuery\Shadow;

use ZtdQuery\Exception\MissingPrimaryKeyException;
use ZtdQuery\Schema\RowSet;
use ZtdQuery\Schema\TableDefinition;
use ZtdQuery\Shadow\Row\RowMatch;

/**
 * Holds in-memory shadow rows for tables.
 *
 * @phpstan-import-type Row from TableDefinition
 *
 * @visibility public
 *
 * @example Snapshot and restore fixture rows
 *     $store = new \ZtdQuery\Shadow\ShadowStore();
 *     $store->set('users', [['id' => 1, 'name' => 'Alice']]);
 *     $snapshot = $store->snapshot();
 *     $store->update('users', [['id' => 1, 'name' => 'Bob']], ['id']);
 *     $store->get('users') // => [['id' => 1, 'name' => 'Bob']]
 *     $store->restore($snapshot);
 *     $store->get('users') // => [['id' => 1, 'name' => 'Alice']]
 */
class ShadowStore
{
    /**
     * @param RowMatch $match Decides when a stored row is the row a caller means
     */
    public function __construct(private readonly RowMatch $match = new RowMatch())
    {
    }

    /**
     * Shadow rows keyed by table name.
     *
     * @var array<string, RowSet>
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
     * The caller's row keys and values are retained unchanged.
     *
     * @param array<int, Row> $rows Rows the table now has, in order
     */
    public function set(string $tableName, array $rows): void
    {
        $this->fixtures[$tableName] = new RowSet($rows);
        $this->initializedTables[$tableName] = $tableName;
    }

    /**
     * Get all shadow rows for a table.
     *
     * @return array<int, Row>
     */
    public function get(string $tableName): array
    {
        return ($this->fixtures[$tableName] ?? new RowSet())->rows;
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
     * @return array<string, array<int, Row>>
     */
    public function getAll(): array
    {
        $tables = [];
        foreach ($this->fixtures as $name => $snapshot) {
            $tables[$name] = $snapshot->rows;
        }

        return $tables;
    }

    /**
     * Remove all shadow data.
     */
    public function clear(): void
    {
        $this->fixtures = [];
        $this->initializedTables = [];
    }

    /**
     * Snapshot.
     *
     * @return self
     */
    public function snapshot(): self
    {
        return clone $this;
    }

    /**
     * Restore.
     *
     * @param self $snapshot
     */
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
            $this->fixtures[$tableName] = new RowSet();
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
     * @param array<int, Row> $rows
     */
    public function insert(string $tableName, array $rows): void
    {
        $current = $this->get($tableName);
        $this->fixtures[$tableName] = new RowSet(array_merge($current, $rows));
    }

    /**
     * Delete rows from the shadow set.
     *
     * @param array<int, Row> $deletedRows
     * @param array<int, string> $primaryKeys
     */
    public function delete(string $tableName, array $deletedRows, array $primaryKeys = []): void
    {
        if (!isset($this->fixtures[$tableName])) {
            return;
        }

        $currentRows = $this->fixtures[$tableName]->rows;
        $remainingRows = [];

        foreach ($currentRows as $currentRow) {
            $isDeleted = false;
            foreach ($deletedRows as $deletedRow) {
                if ($this->match->identifies($currentRow, $deletedRow, $primaryKeys)) {
                    $isDeleted = true;
                    break;
                }
            }

            if (!$isDeleted) {
                $remainingRows[] = $currentRow;
            }
        }

        $this->fixtures[$tableName] = new RowSet($remainingRows);
    }

    /**
     * Update rows matched by primary keys.
     *
     * @param array<int, Row> $updatedRows
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

        $currentRows = $this->fixtures[$tableName]->rows;

        foreach ($updatedRows as $updatedRow) {
            foreach ($currentRows as &$currentRow) {
                if ($this->match->identifies($currentRow, $updatedRow, $primaryKeys)) {
                    $currentRow = $updatedRow;
                    break;
                }
            }
        }
        unset($currentRow);
        $this->fixtures[$tableName] = new RowSet($currentRows);
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

        $currentRows = $this->fixtures[$tableName]->rows;
        foreach ($updates as $update) {
            foreach ($currentRows as &$currentRow) {
                if ($this->match->identifies($currentRow, $update['identity'], $primaryKeys)) {
                    $currentRow = $update['row'];
                    break;
                }
            }
        }
        unset($currentRow);
        $this->fixtures[$tableName] = new RowSet($currentRows);
    }
}
