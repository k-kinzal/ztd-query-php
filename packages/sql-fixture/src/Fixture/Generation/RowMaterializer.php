<?php

declare(strict_types=1);

namespace SqlFixture\Fixture\Generation;

use Faker\Generator;
use SqlFixture\Fixture\GenerationRun;
use SqlFixture\Fixture\RowGeneration;
use SqlFixture\Fixture\RowSpec;
use SqlFixture\Plan\FixturePlan;
use SqlFixture\Plan\Relation;
use SqlFixture\Schema\SchemaResolverInterface;
use SqlFixture\Schema\TableSchema;

/**
 * Walks the relations needed to materialize one connected fixture group.
 *
 */
final class RowMaterializer
{
    /**
     * Uses one schema registry, row generator and random source for the walk.
     */
    public function __construct(
        private readonly SchemaResolverInterface $schemas,
        private readonly RowGeneration $generator,
        private readonly Generator $faker,
    ) {
    }

    /**
     * @param array<mixed> $inherited Columns already fixed by the relation walked in on
     * @param list<string> $path
     * @throws RecursiveRelationException
     */
    public function materialize(
        FixturePlan $plan,
        string $table,
        array $inherited,
        int $count,
        bool $isList,
        ?Relation $arrivedBy,
        GenerationRun $run,
        array $path = [],
    ): void {
        $selfReference = $arrivedBy !== null && $arrivedBy->parent()->table === $table && $arrivedBy->child()->table === $table;
        if (in_array($table, $path, true) && (!$selfReference || count(array_keys($path, $table, true)) > 1)) {
            throw new RecursiveRelationException($path, $table);
        }
        $path[] = $table;
        $run->reached($table, $isList);

        $schema = $this->schemas->resolve($table);
        $spec = $run->specFor($table);

        for ($index = 0; $index < $count; $index++) {
            $this->materializeRow($plan, $schema, $inherited, $spec, $index, $isList, $arrivedBy, $run, $path);
        }
    }

    /**
     * Generate the single row a relation points at.
     * @param list<string> $path
     * @param array<mixed> $inherited
     * @return array<string, mixed>
     */
    public function materializeParent(
        FixturePlan $plan,
        string $table,
        bool $isList,
        Relation $arrivedBy,
        GenerationRun $run,
        array $path = [],
        array $inherited = [],
    ): array {
        $this->materialize($plan, $table, $inherited, 1, $isList, $arrivedBy, $run, $path);

        return $run->lastRow($table);
    }

    /**
     * @param array<mixed> $inherited
     * @param list<string> $path
     */
    public function materializeRow(
        FixturePlan $plan,
        TableSchema $schema,
        array $inherited,
        RowSpec $spec,
        int $index,
        bool $isList,
        ?Relation $arrivedBy,
        GenerationRun $run,
        array $path = [],
    ): void {
        $overrides = $spec->overridesFor($index);
        $resolved = (new \SqlFixture\Fixture\Choice\RowChoices())->resolve(
            $plan,
            $schema->tableName,
            (new RowBindings())->merge($schema->tableName, $inherited, $overrides),
            $arrivedBy,
            $run,
            $this->faker
        );
        $plan = $resolved->plan;
        $arrivedBy = $resolved->arrivedBy;
        $fixed = $resolved->values;

        foreach ((new \SqlFixture\Fixture\Choice\InverseRelations())->unique($plan, $plan->dependenciesOf($schema->tableName)) as $relation) {
            if ($relation === $arrivedBy) {
                continue;
            }

            $fixed = (new RowBindings())->merge($schema->tableName, $fixed, $this->toParent($plan, $relation, $fixed, $isList, $run, $path));
        }

        $row = $run->record(
            $schema,
            $this->generator->generate($schema, $fixed),
            (new RelationProjection())->referencedColumns($plan, $schema->tableName)
        );

        foreach ((new \SqlFixture\Fixture\Choice\InverseRelations())->unique($plan, $plan->dependentsOf($schema->tableName)) as $relation) {
            if ($relation === $arrivedBy) {
                continue;
            }

            $this->toChildren($plan, $relation, $row, $isList, $run, $path);
        }
    }

    /**
     * Generate the one row this one references and read the linking columns
     * off it.
     *
     * Where the caller has already fixed every linking column they have said
     * what the row references, so no parent is invented to contradict them.
     *
     * @param array<mixed> $overrides
     * @param list<string> $path
     * @return array<string, mixed>
     */
    public function toParent(
        FixturePlan $plan,
        Relation $relation,
        array $overrides,
        bool $isList,
        GenerationRun $run,
        array $path = [],
    ): array {
        if ((new RelationProjection())->isAlreadyLinked($relation, $overrides)) {
            return [];
        }

        if ($relation->parentIsOptional() && !$run->wasAskedFor($relation->parent()->table)) {
            return [];
        }

        $inherited = [];
        foreach ($relation->columnMap() as $childColumn => $parentColumn) {
            if (array_key_exists($childColumn, $overrides)) {
                $inherited[$parentColumn] = $overrides[$childColumn];
            }
        }
        $parent = $this->materializeParent($plan, $relation->parent()->table, $isList, $relation, $run, $path, $inherited);

        return (new RelationProjection())->project($parent, $relation);
    }

    /**
     * @param array<string, mixed> $row
     * @param list<string> $path
     */
    public function toChildren(
        FixturePlan $plan,
        Relation $relation,
        array $row,
        bool $isList,
        GenerationRun $run,
        array $path = [],
    ): void {
        $child = $relation->child()->table;

        $this->materialize(
            $plan,
            $child,
            (new RelationProjection())->project($row, $relation),
            (new RelationCounts($this->faker))->resolveCount($run->specFor($child), $relation),
            $isList || $relation->childIsCollection(),
            $relation,
            $run,
            $path
        );
    }
}
