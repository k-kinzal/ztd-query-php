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
     */
    public function materialize(
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
    public function materializeParent(
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
     * @param array<mixed> $inherited
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
            (new RelationProjection())->referencedColumns($plan, $schema->tableName)
        );

        foreach ($plan->dependentsOf($schema->tableName) as $relation) {
            if ($relation === $arrivedBy) {
                continue;
            }

            $this->toChildren($plan, $relation, $row, $isList, $run);
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
     * @return array<string, mixed>
     */
    public function toParent(
        FixturePlan $plan,
        Relation $relation,
        array $overrides,
        bool $isList,
        GenerationRun $run,
    ): array {
        if ((new RelationProjection())->isAlreadyLinked($relation, $overrides)) {
            return [];
        }

        if ($relation->parentIsOptional() && !$run->wasAskedFor($relation->parent()->table)) {
            return [];
        }

        $parent = $this->materializeParent($plan, $relation->parent()->table, $isList, $relation, $run);

        return (new RelationProjection())->project($parent, $relation);
    }

    /**
     * @param array<string, mixed> $row
     */
    public function toChildren(
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
            (new RelationProjection())->project($row, $relation),
            (new RelationCounts($this->faker))->resolveCount($run->specFor($child), $relation),
            $isList || $relation->childIsCollection(),
            $relation,
            $run
        );
    }
}
