<?php

declare(strict_types=1);

namespace MySqlMemory\Dictionary;

use SqlSemantics\Statement\Declaration\Table;

/**
 * The databases of a server and their tables.
 *
 * Database names are compared exactly, as the server compares them with lower_case_table_names=0.
 *
 * @visibility MySqlMemory
 */
final class Dictionary
{
    /**
     * @param array<string, Schema> $schemas The databases, by name
     */
    public function __construct(public array $schemas = [])
    {
    }

    /**
     * Finds a database by name, or answers null.
     */
    public function schema(string $name): ?Schema
    {
        return $this->schemas[$name] ?? null;
    }

    /**
     * Finds a table of a database, or answers null.
     */
    public function table(string $schema, string $name): ?StoredTable
    {
        return $this->schemas[$schema]?->table($name) ?? null;
    }

    /**
     * Answers the declarations of every table, for binding a statement.
     *
     * @return list<Table>
     */
    public function declarations(): array
    {
        $declarations = [];
        foreach ($this->schemas as $schema) {
            foreach ($schema->tables as $table) {
                $declarations[] = $table->definition->declaration;
            }
        }

        return $declarations;
    }
}
