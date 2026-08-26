<?php

declare(strict_types=1);

namespace SqlFixture\Fixture;

use SqlFixture\Plan\FixturePlan;
use SqlFixture\Schema\TableSchema;
use SqlFixture\TypeMapper\TypeMapperInterface;

/**
 * The rows produced while one plan is being walked, and what was learnt about
 * them on the way.
 *
 * Whether a table reads back as a list is decided by how the walk reached it,
 * not by the relation on its own: the table a plan is about is one row even
 * when something else references it.
 *
 * @phpstan-import-type FixtureRow from TypeMapperInterface
 * @phpstan-import-type FixtureValue from TypeMapperInterface
 */
final class GenerationRun
{
    /** @var array<string, list<FixtureRow>> */
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

    /**
     * Reports whether a table has already been reached by the walk.
     *
     * @param string $table Table to answer for
     *
     * @return bool True when rows of it have been generated
     */
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

    /**
     * Answers what the caller asked for on a table.
     *
     * @param string $table Table to answer for
     *
     * @return RowSpec What the caller wrote, or a description that fixes nothing
     */
    public function specFor(string $table): RowSpec
    {
        return $this->specs[$table] ?? RowSpec::unspecified();
    }

    /**
     * Keep a row, standing in for the keys a database would have assigned
     * where a relation needs to read them.
     *
     * Only the columns something references are filled. A row nothing points
     * at reads back the way fixture() has always returned one, with the
     * auto-increment column absent because the database supplies it.
     *
     * @param FixtureRow $row
     * @param list<string> $referencedColumns Columns a relation reads off this table
     * @return FixtureRow
     */
    public function record(TableSchema $schema, array $row, array $referencedColumns = []): array
    {
        foreach ($referencedColumns as $columnName) {
            $column = $schema->getColumn($columnName);

            if ($column === null || !$column->autoIncrement || array_key_exists($columnName, $row)) {
                continue;
            }

            $row[$columnName] = count($this->rows[$schema->tableName] ?? []) + 1;
        }

        $this->rows[$schema->tableName][] = $row;

        return $row;
    }

    /**
     * The row most recently kept for a table, which is the one a relation
     * walked into it will read its key from.
     *
     * @return FixtureRow
     */
    public function lastRow(string $table): array
    {
        $rows = $this->rows[$table] ?? [];

        return $rows[count($rows) - 1] ?? [];
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
