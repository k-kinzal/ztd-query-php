<?php

declare(strict_types=1);

namespace MySqlMemory\Dictionary;

use LogicException;
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
            foreach (array_merge(array_values($schema->procedures), array_values($schema->functions)) as $routine) {
                $routine->metadataLoaded = false;
            }
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
     * The server's open table handles, independent of its table definitions.
     */
    public readonly TableCache $cache;

    /**
     * Invalidates session routine lookups after a stored routine definition changes.
     */
    public int $routineGeneration = 0;

    /**
     * @param array<string, Schema> $schemas The databases, by name
     */
    public function __construct(public array $schemas = [])
    {
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
     * Finds a table in an explicit session namespace, or in the persistent catalog.
     */
    public function table(string $schema, string $name, ?Temporaries $temporaries = null): ?StoredTable
    {
        return $this->schema($schema) === null ? null : $temporaries?->table($schema, $name) ?? $this->schema($schema)->table($name);
    }

    /**
     * Stores a definition in its persistent or explicitly supplied temporary namespace.
     *
     * @throws LogicException When a temporary definition has no owning session namespace
     */
    public function store(StoredTable $table, ?Temporaries $temporaries = null): void
    {
        $definition = $table->definition;
        if ($definition->temporary) {
            if ($temporaries === null) {
                throw new LogicException('A temporary table requires its owning session namespace.');
            }
            $temporaries->add($table);

            return;
        }
        $this->schemas[$definition->schema]->tables[$definition->name] = $table;
    }

    /**
     * Removes a table stored under a database and name, a temporary one or a base one.
     *
     * @throws LogicException When a temporary definition is not owned by the supplied namespace
     */
    public function release(StoredTable $table, string $schema, string $name, ?Temporaries $temporaries = null): void
    {
        if ($table->definition->temporary) {
            if ($temporaries === null || $temporaries->table($schema, $name) !== $table) {
                throw new LogicException('Releasing a temporary table requires its owning session namespace.');
            }
            $temporaries->remove($schema, $name);

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
    public function declarations(?Temporaries $temporaries = null): array
    {
        $declarations = [];
        foreach ($this->schemas as $schema) {
            foreach ($temporaries->tables[$schema->name] ?? [] as $table) {
                $declarations[] = $table->definition->declaration;
            }
            foreach ($schema->tables as $table) {
                if ($temporaries?->table($schema->name, $table->definition->name) === null) {
                    $declarations[] = $table->definition->declaration;
                }
            }
            foreach ($schema->views as $view) {
                if ($temporaries?->table($schema->name, $view->name) === null) {
                    $declarations[] = $view->declaration;
                }
            }
        }

        return [...$declarations, ...($this->system?->declarations() ?? [])];
    }
}
