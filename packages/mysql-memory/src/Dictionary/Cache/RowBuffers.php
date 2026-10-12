<?php

declare(strict_types=1);

namespace MySqlMemory\Dictionary\Cache;

use MySqlMemory\Dictionary\StoredTable;
use MySqlMemory\Evaluation\Leaf\BufferedRead;
use WeakMap;

/**
 * Retains table fields for each connection, separately from stored rows and query output.
 *
 * Reads and writes replace the fields of that connection's table handle. Closing a persistent
 * handle discards them; temporary handles survive FLUSH TABLES. A failed primary-key lookup
 * retains NULL bits only for nullable columns across statements (verified through SQL on MySQL 8.4).
 *
 * @visibility MySqlMemory
 */
final class RowBuffers
{
    /**
     * @var WeakMap<object, WeakMap<StoredTable, array<int, list<int|float|string|null>>>>
     */
    private WeakMap $connections;

    /**
     * Starts without any connection-owned table fields.
     */
    public function __construct()
    {
        $this->connections = new WeakMap();
    }

    /**
     * Answers the retained fields, initially non-NULL zero values even for nullable columns.
     *
     * @param object $connection The connection's lifetime identity
     * @return list<int|float|string|null>
     */
    public function read(object $connection, StoredTable $table, int $handle = 0): array
    {
        return $this->connections[$connection][$table][$handle] ?? array_map(static fn ($column): int|float|string|null => (new BufferedRead($column->domain->withNullable(false), 0))->initial(), $table->definition->columns);
    }

    /**
     * Retains a row read or written through one connection's table handle.
     *
     * @param object $connection The connection's lifetime identity
     * @param list<int|float|string|null> $row The fields in storage column order
     */
    public function retain(object $connection, StoredTable $table, array $row, int $handle = 0): void
    {
        /** @var WeakMap<StoredTable, array<int, list<int|float|string|null>>> $tables */
        $tables = $this->connections[$connection] ?? new WeakMap();
        $rows = $tables[$table] ?? [];
        $rows[$handle] = $row;
        $tables[$table] = $rows;
        $this->connections[$connection] = $tables;
    }

    /**
     * Retains the nullable NULL bits after a lookup finds no row, preserving NOT NULL fields.
     */
    public function missing(object $connection, StoredTable $table, int $handle = 0): void
    {
        $row = $this->read($connection, $table, $handle);
        foreach ($table->definition->columns as $position => $column) {
            if ($column->nullable()) {
                array_splice($row, $position, 1, [null]);
            }
        }
        $this->retain($connection, $table, $row, $handle);
    }

    /**
     * Discards persistent handles for the named table, database, or every database.
     */
    public function close(?string $schema = null, ?string $name = null): void
    {
        foreach ($this->connections as $tables) {
            foreach (clone $tables as $table => $row) {
                $definition = $table->definition;
                if (!$definition->temporary && ($schema === null || $schema === $definition->schema) && ($name === null || $name === $definition->name)) {
                    unset($tables[$table]);
                }
            }
        }
    }
}
