<?php

declare(strict_types=1);

namespace MySqlMemory\Dictionary;

use MySqlMemory\Dictionary\Cache\TableCache;
use MySqlMemory\System\SystemSchemas;
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
     * Discards MEMORY table rows after sessions end for restart, retaining their definitions.
     *
     * Source: https://dev.mysql.com/doc/refman/8.4/en/memory-storage-engine.html.
     */
    public function discardVolatileRows(): void
    {
        $this->cache->close();
        foreach ($this->schemas as $schema) {
            foreach ($schema->tables as $table) {
                $table->updated = strcasecmp($table->definition->engine, 'MyISAM') === 0 ? $table->updated : null;
                $table->statistics = [];
                $table->statisticsRead = null;
                if (strcasecmp($table->definition->engine, 'MEMORY') === 0) {
                    $table->data = new \MySqlMemory\Storage\Heap();
                }
            }
        }
    }

    /**
     * The tables of the system databases, or null for a dictionary without them.
     */
    public ?SystemSchemas $system = null;

    /**
     * The temporary tables of the session whose statement runs, which hide the base tables of their names from it.
     */
    public Temporaries $temporaries;

    /**
     * The server's open table handles, independent of its table definitions.
     */
    public readonly TableCache $cache;

    /**
     * @param array<string, Schema> $schemas The databases, by name
     */
    public function __construct(public array $schemas = [])
    {
        $this->temporaries = new Temporaries();
        $this->cache = new TableCache();
    }

    /**
     * Finds a database by name, or answers null; INFORMATION_SCHEMA is named in any case.
     *
     * Source: https://dev.mysql.com/doc/refman/8.4/en/identifier-case-sensitivity.html.
     */
    public function schema(string $name): ?Schema
    {
        return $this->schemas[$name] ?? (strcasecmp($name, 'information_schema') === 0 ? $this->schemas['information_schema'] ?? null : null);
    }

    /**
     * Answers the type each stored function of the server returns, by `database.name`.
     *
     * @return array<string, \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Domain>
     */
    public function functions(): array
    {
        $functions = [];
        foreach ($this->schemas as $schema) {
            foreach ($schema->functions as $function) {
                $functions[$schema->name . '.' . $function->name] = $function->returned()->resolved();
            }
        }

        return $functions;
    }

    /**
     * Finds a table of a database, or answers null: a temporary table of the session whose statement runs before a base table.
     */
    public function table(string $schema, string $name): ?StoredTable
    {
        return $this->schema($schema) === null ? null : $this->temporaries->table($schema, $name) ?? $this->schema($schema)->table($name);
    }

    /**
     * Stores a table under the database and name its definition holds: a temporary table among the temporary tables of the session whose statement runs.
     */
    public function store(StoredTable $table): void
    {
        $definition = $table->definition;
        if ($definition->temporary) {
            $this->temporaries->add($table);

            return;
        }
        $this->schemas[$definition->schema]->tables[$definition->name] = $table;
    }

    /**
     * Removes a table stored under a database and name, a temporary one or a base one.
     */
    public function release(StoredTable $table, string $schema, string $name): void
    {
        if ($this->temporaries->table($schema, $name) === $table) {
            $this->temporaries->remove($schema, $name);

            return;
        }
        unset($this->schemas[$schema]->tables[$name]);
        $this->cache->close($schema, $name);
    }

    /**
     * Makes the foreign keys that reference a table follow it to a new name.
     */
    public function retarget(string $schema, string $name, string $toSchema, string $toName): void
    {
        foreach ($this->schemas as $database) {
            foreach ($database->tables as $table) {
                $keys = array_map(static fn (ForeignKey $key): ForeignKey => $key->references($schema, $name) ? $key->retargeted($toSchema, $toName) : $key, $table->definition->foreignKeys);
                if ($keys !== $table->definition->foreignKeys) {
                    $table->definition = $table->definition->withConstraints($table->definition->checks, $keys);
                }
            }
        }
    }

    /**
     * Answers the declarations of every table, for binding a statement: those of the system tables last.
     *
     * @return list<Table>
     */
    public function declarations(): array
    {
        $declarations = [];
        foreach ($this->schemas as $schema) {
            foreach ($this->temporaries->tables[$schema->name] ?? [] as $table) {
                $declarations[] = $table->definition->declaration;
            }
            foreach ($schema->tables as $name => $table) {
                if ($this->temporaries->table($schema->name, $name) === null) {
                    $declarations[] = $table->definition->declaration;
                }
            }
            foreach ($schema->views as $view) {
                $declarations[] = $view->declaration;
            }
        }

        return [...$declarations, ...($this->system?->declarations() ?? [])];
    }
}
