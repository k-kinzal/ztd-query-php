<?php

declare(strict_types=1);

namespace ZtdQuery\Schema;

/**
 * Stores CREATE TABLE definitions keyed by table name.
 */
final class SchemaRegistry
{
    /**
     * Cached CREATE TABLE statements by table name.
     *
     * @var array<string, string>
     */
    private array $schemas = [];

    /**
     * Register or replace a CREATE TABLE statement.
     */
    public function register(string $tableName, string $createTableSql): void
    {
        $this->schemas[$tableName] = $createTableSql;
    }

    /**
     * Return a CREATE TABLE statement when present.
     */
    public function get(string $tableName): ?string
    {
        return $this->schemas[$tableName] ?? null;
    }

    /**
     * Check whether a table is explicitly registered.
     */
    public function has(string $tableName): bool
    {
        return isset($this->schemas[$tableName]);
    }

    /**
     * Return all registered CREATE TABLE statements.
     *
     * @return array<string, string>
     */
    public function getAll(): array
    {
        return $this->schemas;
    }

    /**
     * Check whether at least one table is registered.
     */
    public function hasAnyTables(): bool
    {
        return $this->schemas !== [];
    }

    /**
     * Remove all registered tables.
     */
    public function clear(): void
    {
        $this->schemas = [];
    }

    /**
     * Remove a registered table.
     */
    public function unregister(string $tableName): void
    {
        unset($this->schemas[$tableName]);
    }
}
