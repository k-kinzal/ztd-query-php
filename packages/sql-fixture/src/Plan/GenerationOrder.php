<?php

declare(strict_types=1);

namespace SqlFixture\Plan;

/**
 * Orders the tables of a plan so a parent is always generated before its children.
 *
 * A child row carries values read off its parent, so the parent has to exist
 * first. Repeatedly taking every table that is waiting for nothing still
 * pending gives that order, and a round where nothing is ready means the
 * remaining tables are waiting on each other.
 *
 * A table that references itself is left out of the waiting: it cannot precede
 * itself, and the only self references a plan may declare are the optional
 * ones, which finish on their own.
 */
final class GenerationOrder
{
    /**
     * Orders the tables so every parent precedes its children.
     *
     * @param list<string> $tables Every table the plan names
     * @param list<Relation> $relations Relations the plan declares
     *
     * @return list<string> The same tables, in an order that can be generated
     *
     * @throws PlanStructureException When the relations leave a group of tables waiting on each other
     */
    public function of(array $tables, array $relations): array
    {
        $pending = $tables;
        $ordered = [];

        while ($pending !== []) {
            $ready = [];
            $waiting = [];
            foreach ($pending as $table) {
                if ($this->waitsForAny($table, $relations, $pending)) {
                    $waiting[] = $table;
                    continue;
                }
                $ready[] = $table;
            }
            if ($ready === []) {
                throw PlanStructureException::cycle($pending);
            }
            $ordered = [...$ordered, ...$ready];
            $pending = $waiting;
        }

        return $ordered;
    }

    /**
     * Reports whether a table is waiting on a parent that has not been ordered yet.
     *
     * @param string $table Table to answer for
     * @param list<Relation> $relations Relations the plan declares
     * @param list<string> $pending Tables not yet ordered
     *
     * @return bool True when one of its parents is still pending
     */
    public function waitsForAny(string $table, array $relations, array $pending): bool
    {
        foreach ($relations as $relation) {
            $parent = $relation->parent()->table;
            if ($relation->child()->table !== $table || $parent === $table) {
                continue;
            }
            if (in_array($parent, $pending, true)) {
                return true;
            }
        }

        return false;
    }
}
