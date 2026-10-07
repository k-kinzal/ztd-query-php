<?php

declare(strict_types=1);

namespace MySqlMemory\Dictionary;

/**
 * A database: its name, default collation and tables.
 *
 * Table names are compared exactly, as the server compares them with lower_case_table_names=0.
 *
 * @visibility MySqlMemory
 */
final class Schema
{
    /**
     * @param string $name The database name
     * @param string $collation The default collation of its tables
     * @param array<string, StoredTable> $tables The tables, by name
     */
    public function __construct(public readonly string $name, public string $collation = 'utf8mb4_0900_ai_ci', public array $tables = [])
    {
    }

    /**
     * Finds a table by name, or answers null.
     */
    public function table(string $name): ?StoredTable
    {
        return $this->tables[$name] ?? null;
    }
}
