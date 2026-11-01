<?php

declare(strict_types=1);

namespace MySqlMemory\Dictionary;

/**
 * The temporary tables of one session, by database and name.
 *
 * Only the session that creates a temporary table sees it, and it ends with the session. While
 * it exists it hides a base table of the same name from that session; SHOW TABLES and
 * INFORMATION_SCHEMA list base tables only.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/create-temporary-table.html.
 *
 * @visibility MySqlMemory
 */
final class Temporaries
{
    /**
     * @param array<string, array<string, StoredTable>> $tables The temporary tables, by database and name
     */
    public function __construct(public array $tables = [])
    {
    }

    /**
     * Finds a temporary table, or answers null.
     */
    public function table(string $schema, string $name): ?StoredTable
    {
        return $this->tables[$schema][$name] ?? null;
    }

    /**
     * Adds a temporary table under the database and name its definition holds.
     */
    public function add(StoredTable $table): void
    {
        $this->tables[$table->definition->schema][$table->definition->name] = $table;
    }

    /**
     * Removes a temporary table.
     */
    public function remove(string $schema, string $name): void
    {
        unset($this->tables[$schema][$name]);
        if (($this->tables[$schema] ?? null) === []) {
            unset($this->tables[$schema]);
        }
    }
}
