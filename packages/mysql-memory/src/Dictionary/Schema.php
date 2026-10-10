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
     * @var array<string, Routine> The stored procedures, by lower-case name
     */
    public array $procedures = [];

    /**
     * @var array<string, Routine> The stored functions, by lower-case name
     */
    public array $functions = [];

    /**
     * @var list<Trigger> The triggers, in the order they fire for each table, time and event
     */
    public array $triggers = [];

    /**
     * @var array<string, Event> The events, by lower-case name
     */
    public array $events = [];

    /**
     * @var array<string, View> The views, by name
     */
    public array $views = [];

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
