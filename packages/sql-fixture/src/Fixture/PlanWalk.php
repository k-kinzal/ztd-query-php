<?php

declare(strict_types=1);

namespace SqlFixture\Fixture;

use SqlFixture\FixtureGenerator;
use SqlFixture\Plan\FixturePlan;
use SqlFixture\Plan\Relation;
use SqlFixture\Schema\SchemaNotFoundException;
use SqlFixture\Schema\SchemaResolverInterface;
use SqlFixture\Schema\TableSchema;

/**
 * Generates the rows of one plan, following its relations outwards.
 *
 * The plan and the run being filled are fixed for the whole walk, which is
 * what lets each step be written as a question about where it has got to
 * rather than as a step that carries everything with it.
 *
 * Direction decides count. Walking to a child asks how many; walking to a
 * parent asks for the one row being referenced. Both carry the
 * collection-ness of where they came from, so a table reached through a list
 * is a list however many rows it happens to have. The relation walked in on is
 * skipped on the way back out, which is what keeps the walk from going round.
 */
final class PlanWalk
{
    /**
     * @param FixturePlan $plan Plan being generated
     * @param GenerationRun $run Rows generated so far
     * @param SchemaResolverInterface $schemas Answers what a table looks like
     * @param FixtureGenerator $generator Builds one row against a table
     * @param ChildRowCount $count Decides how many child rows a relation gets
     */
    public function __construct(
        private readonly FixturePlan $plan,
        private readonly GenerationRun $run,
        private readonly SchemaResolverInterface $schemas,
        private readonly FixtureGenerator $generator,
        private readonly ChildRowCount $count,
    ) {
    }

    /**
     * Generates rows of one table and everything they reach.
     *
     * @param string $table Table to generate
     * @param array<string, mixed> $inherited Columns already fixed by the relation walked in on
     * @param int $rows How many rows to generate
     * @param bool $isList Whether this table was reached through a collection
     * @param Relation|null $arrivedBy Relation walked in on, not followed back out
     *
     * @throws PlanSchemaException When a relation reads a column the parent row does not carry
     * @throws SchemaNotFoundException When the plan names a table nothing can resolve
     */
    public function materialize(
        string $table,
        array $inherited,
        int $rows,
        bool $isList,
        ?Relation $arrivedBy,
    ): void {
        $this->run->reached($table, $isList);
        $schema = $this->schemas->resolve($table);
        $spec = $this->run->specFor($table);

        for ($index = 0; $index < $rows; $index++) {
            $this->row($schema, $inherited, $spec, $index, $isList, $arrivedBy);
        }
    }

    /**
     * Generates one row, its parents before it and its children after.
     *
     * @param TableSchema $schema Table being generated
     * @param array<string, mixed> $inherited Columns already fixed by the relation walked in on
     * @param RowSpec $spec What the caller asked for on this table
     * @param int $index Which row of this table is being generated
     * @param bool $isList Whether this table was reached through a collection
     * @param Relation|null $arrivedBy Relation walked in on, not followed back out
     *
     * @throws PlanSchemaException When a relation reads a column the parent row does not carry
     * @throws SchemaNotFoundException When the plan names a table nothing can resolve
     */
    public function row(
        TableSchema $schema,
        array $inherited,
        RowSpec $spec,
        int $index,
        bool $isList,
        ?Relation $arrivedBy,
    ): void {
        $overrides = $spec->overridesFor($index);
        $fixed = $inherited;
        foreach ($this->plan->dependenciesOf($schema->tableName) as $relation) {
            if ($relation !== $arrivedBy) {
                $fixed = array_merge($fixed, $this->linkToParent($relation, $overrides, $isList));
            }
        }
        $fixed = array_merge($fixed, $overrides);

        $row = $this->run->record(
            $schema,
            $this->generator->generate($schema, $fixed),
            $this->plan->columnsReferencedOn($schema->tableName),
        );

        foreach ($this->plan->dependentsOf($schema->tableName) as $relation) {
            if ($relation !== $arrivedBy) {
                $this->linkToChildren($relation, $row, $isList);
            }
        }
    }

    /**
     * Generates the one row this one references, and reads the linking columns off it.
     *
     * Where the caller has already fixed every linking column they have said
     * what the row references, so no parent is invented to contradict them. An
     * optional parent nobody asked for is left ungenerated, which is what
     * makes the reference null.
     *
     * @param Relation $relation Relation to the parent
     * @param array<string, mixed> $overrides Values the caller fixed on this row
     * @param bool $isList Whether this table was reached through a collection
     *
     * @return array<string, mixed> Linking columns and the values they must carry
     *
     * @throws PlanSchemaException When the parent row does not carry a column the relation reads
     * @throws SchemaNotFoundException When the plan names a table nothing can resolve
     */
    public function linkToParent(Relation $relation, array $overrides, bool $isList): array
    {
        if ($relation->isSatisfiedBy($overrides)) {
            return [];
        }
        if ($relation->parentIsOptional() && !$this->run->wasAskedFor($relation->parent()->table)) {
            return [];
        }

        $table = $relation->parent()->table;
        $this->materialize($table, [], 1, $isList, $relation);

        return $relation->project($this->run->lastRow($table));
    }

    /**
     * Generates the rows that reference this one.
     *
     * @param Relation $relation Relation to the children
     * @param array<string, mixed> $row Row they reference
     * @param bool $isList Whether this table was reached through a collection
     *
     * @throws PlanSchemaException When this row does not carry a column the relation reads
     * @throws SchemaNotFoundException When the plan names a table nothing can resolve
     */
    public function linkToChildren(Relation $relation, array $row, bool $isList): void
    {
        $child = $relation->child()->table;

        $this->materialize(
            $child,
            $relation->project($row),
            $this->count->of($this->run->specFor($child), $relation),
            $isList || $relation->childIsCollection(),
            $relation,
        );
    }
}
