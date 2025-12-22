<?php

declare(strict_types=1);

namespace ZtdQuery\Schema;

/**
 * Stores TableDefinition instances keyed by table name.
 */
final class TableDefinitionRegistry
{
    /**
     * @var array<string, TableDefinition>
     */
    private array $definitions = [];

    /**
     * Register or replace a table definition.
     */
    public function register(string $tableName, TableDefinition $definition): void
    {
        $this->definitions[$tableName] = $definition;
    }

    /**
     * Return a table definition when present.
     */
    public function get(string $tableName): ?TableDefinition
    {
        return $this->definitions[$tableName] ?? null;
    }

    /**
     * Check whether a table is registered.
     */
    public function has(string $tableName): bool
    {
        return isset($this->definitions[$tableName]);
    }

    /**
     * Return all registered table definitions.
     *
     * @return array<string, TableDefinition>
     */
    public function getAll(): array
    {
        return $this->definitions;
    }

    /**
     * Check whether at least one table is registered.
     */
    public function hasAnyTables(): bool
    {
        return $this->definitions !== [];
    }

    /**
     * Remove all registered tables.
     */
    public function clear(): void
    {
        $this->definitions = [];
    }

    /**
     * Remove a registered table.
     */
    public function unregister(string $tableName): void
    {
        unset($this->definitions[$tableName]);
    }
}
