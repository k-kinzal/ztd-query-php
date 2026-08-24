<?php

declare(strict_types=1);

namespace SqlFixture\Fixture;

use Faker\Generator;
use SqlFixture\FixtureGenerator;
use SqlFixture\Plan\FixturePlan;
use SqlFixture\Plan\Relation;
use SqlFixture\Schema\SchemaResolverInterface;
use SqlFixture\Schema\TableSchema;

/**
 * Generates the rows a plan describes, keeping related rows consistent.
 *
 * The walk starts at the table the plan is about and follows relations
 * outwards, once per connected group of tables. Starting anywhere else changes
 * the answer: a plan written `order.id < order_detail.order_id,
 * order.customer_id > customer.id` has customer as its only dependency-free
 * table, and beginning there would make order a collection hanging off it
 * rather than the one row the plan is about.
 *
 * Direction decides count. Walking to a child asks how many, walking to a
 * parent asks for the one row being referenced. Both carry the collection-ness
 * of where they came from, so a table reached through a list is a list however
 * many rows it happens to have.
 */
final class PlanGenerator
{
    private const DEFAULT_SPREAD = 4;

    public function __construct(
        private readonly SchemaResolverInterface $schemas,
        private readonly FixtureGenerator $generator,
        private readonly Generator $faker,
    ) {
    }

    /**
     * @param array<string, int|array<mixed>|TableOverrides> $overrides Table name => what to override
     * @throws PlanSchemaException If the plan names a column the schema does not have
     */
    public function generate(FixturePlan $plan, array $overrides = []): FixtureSet
    {
        (new PlanSchemaValidator($this->schemas))->validate($plan);

        $run = new GenerationRun($this->specs($overrides));

        foreach ($plan->tables as $table) {
            if ($run->hasVisited($table)) {
                continue;
            }

            $run->claim($this->connectedTo($plan, $table));
            $this->materialize($plan, $table, [], $run->specFor($table)->count ?? 1, false, null, $run);
        }

        return $run->toSet($plan);
    }

    /**
     * @param array<string, mixed> $inherited Columns already fixed by the relation walked in on
     */
    private function materialize(
        FixturePlan $plan,
        string $table,
        array $inherited,
        int $count,
        bool $isList,
        ?Relation $arrivedBy,
        GenerationRun $run,
    ): void {
        $run->reached($table, $isList);

        $schema = $this->schemas->resolve($table);
        $spec = $run->specFor($table);

        for ($index = 0; $index < $count; $index++) {
            $this->materializeRow($plan, $schema, $inherited, $spec, $index, $isList, $arrivedBy, $run);
        }
    }

    /**
     * Generate the single row a relation points at.
     *
     * @return array<string, mixed>
     */
    private function materializeParent(
        FixturePlan $plan,
        string $table,
        bool $isList,
        Relation $arrivedBy,
        GenerationRun $run,
    ): array {
        $this->materialize($plan, $table, [], 1, $isList, $arrivedBy, $run);

        return $run->lastRow($table);
    }

    /**
     * @param array<string, mixed> $inherited
     */
    private function materializeRow(
        FixturePlan $plan,
        TableSchema $schema,
        array $inherited,
        RowSpec $spec,
        int $index,
        bool $isList,
        ?Relation $arrivedBy,
        GenerationRun $run,
    ): void {
        $overrides = $spec->overridesFor($index);
        $fixed = $inherited;

        foreach ($plan->dependenciesOf($schema->tableName) as $relation) {
            if ($relation === $arrivedBy) {
                continue;
            }

            $fixed = array_merge($fixed, $this->toParent($plan, $relation, $overrides, $isList, $run));
        }

        $fixed = array_merge($fixed, $overrides);

        $row = $run->record(
            $schema,
            $this->generator->generate($schema, $fixed),
            $this->referencedColumns($plan, $schema->tableName)
        );

        foreach ($plan->dependentsOf($schema->tableName) as $relation) {
            if ($relation === $arrivedBy) {
                continue;
            }

            $this->toChildren($plan, $relation, $row, $isList, $run);
        }
    }

    /**
     * The columns of a table that some relation reads off it.
     *
     * @return list<string>
     */
    private function referencedColumns(FixturePlan $plan, string $table): array
    {
        $columns = [];

        foreach ($plan->dependentsOf($table) as $relation) {
            foreach ($relation->parent()->columns as $column) {
                if (!in_array($column, $columns, true)) {
                    $columns[] = $column;
                }
            }
        }

        return $columns;
    }

    /**
     * Generate the one row this one references and read the linking columns
     * off it.
     *
     * Where the caller has already fixed every linking column they have said
     * what the row references, so no parent is invented to contradict them.
     *
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function toParent(
        FixturePlan $plan,
        Relation $relation,
        array $overrides,
        bool $isList,
        GenerationRun $run,
    ): array {
        if ($this->isAlreadyLinked($relation, $overrides)) {
            return [];
        }

        if ($relation->parentIsOptional() && !$run->wasAskedFor($relation->parent()->table)) {
            return [];
        }

        $parent = $this->materializeParent($plan, $relation->parent()->table, $isList, $relation, $run);

        return $this->project($parent, $relation);
    }

    /**
     * @param array<string, mixed> $row
     */
    private function toChildren(
        FixturePlan $plan,
        Relation $relation,
        array $row,
        bool $isList,
        GenerationRun $run,
    ): void {
        $child = $relation->child()->table;

        $this->materialize(
            $plan,
            $child,
            $this->project($row, $relation),
            $this->resolveCount($run->specFor($child), $relation),
            $isList || $relation->childIsCollection(),
            $relation,
            $run
        );
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function isAlreadyLinked(Relation $relation, array $overrides): bool
    {
        foreach (array_keys($relation->columnMap()) as $childColumn) {
            if (!array_key_exists($childColumn, $overrides)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array<string, mixed> $parentRow
     * @return array<string, mixed>
     */
    private function project(array $parentRow, Relation $relation): array
    {
        $values = [];

        foreach ($relation->columnMap() as $childColumn => $parentColumn) {
            if (!array_key_exists($parentColumn, $parentRow)) {
                throw PlanSchemaException::missingValue($childColumn, $relation->parent(), $parentColumn);
            }

            $values[$childColumn] = $parentRow[$parentColumn];
        }

        return $values;
    }

    private function resolveCount(RowSpec $spec, Relation $relation): int
    {
        if ($spec->count !== null) {
            return $spec->count;
        }

        $minimum = $relation->minimumChildRows();
        $maximum = $relation->maximumChildRows() ?? $minimum + self::DEFAULT_SPREAD;

        return $this->faker->numberBetween($minimum, $maximum);
    }

    /**
     * Every table joined to this one by relations, however the arrows point.
     *
     * @return list<string>
     */
    private function connectedTo(FixturePlan $plan, string $table): array
    {
        $found = [$table];

        for ($index = 0; $index < count($found); $index++) {
            foreach ($plan->relations as $relation) {
                $ends = [$relation->left->table, $relation->right->table];

                foreach ([$ends, array_reverse($ends)] as [$from, $to]) {
                    if ($from === $found[$index] && !in_array($to, $found, true)) {
                        $found[] = $to;
                    }
                }
            }
        }

        return $found;
    }

    /**
     * @param array<string, int|array<mixed>|TableOverrides> $overrides
     * @return array<string, RowSpec>
     */
    private function specs(array $overrides): array
    {
        $specs = [];
        foreach ($overrides as $table => $spec) {
            $specs[$table] = RowSpec::from($table, $spec);
        }

        return $specs;
    }
}
