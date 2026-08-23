<?php

declare(strict_types=1);

namespace SqlFixture\Fixture;

use SqlFixture\Plan\FixturePlan;
use SqlFixture\Schema\TableSchema;

/**
 * The rows produced while one plan is being walked, and what was learnt about
 * them on the way.
 *
 * Whether a table reads back as a list is decided by how the walk reached it,
 * not by the relation on its own: the table a plan is about is one row even
 * when something else references it.
 */
final class GenerationRun
{
    /** @var array<string, list<array<string, mixed>>> */
    private array $rows = [];

    /** @var array<string, bool> Table => reads back as a list */
    private array $visited = [];

    /**
     * @param array<string, RowSpec> $specs
     */
    public function __construct(private readonly array $specs)
    {
    }

    /**
     * Take responsibility for a group of tables, so a later pass does not
     * start a second walk into the middle of it.
     *
     * @param list<string> $tables
     */
    public function claim(array $tables): void
    {
        foreach ($tables as $table) {
            $this->visited[$table] ??= false;
        }
    }

    /**
     * Note that the walk arrived at a table, and how.
     */
    public function reached(string $table, bool $asList): void
    {
        $this->visited[$table] = ($this->visited[$table] ?? false) || $asList;
    }

    public function hasVisited(string $table): bool
    {
        return isset($this->visited[$table]);
    }

    /**
     * Whether the caller said anything about this table.
     */
    public function wasAskedFor(string $table): bool
    {
        return isset($this->specs[$table]);
    }

    public function specFor(string $table): RowSpec
    {
        return $this->specs[$table] ?? RowSpec::unspecified();
    }

    /**
     * Keep a row, filling in the keys a database would have assigned so that
     * children have something to reference.
     *
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    public function record(TableSchema $schema, array $row): array
    {
        foreach ($schema->columns as $column) {
            if ($column->autoIncrement && !array_key_exists($column->name, $row)) {
                $row[$column->name] = count($this->rows[$schema->tableName] ?? []) + 1;
            }
        }

        $this->rows[$schema->tableName][] = $row;

        return $row;
    }

    /**
     * The rows gathered, arranged the way the plan reads back.
     */
    public function toSet(FixturePlan $plan): FixtureSet
    {
        $rows = [];
        $lists = [];

        foreach ($plan->tables as $table) {
            $rows[$table] = $this->rows[$table] ?? [];
            $lists[$table] = $this->visited[$table] ?? false;
        }

        return new FixtureSet($rows, $lists, $plan->tables);
    }
}
