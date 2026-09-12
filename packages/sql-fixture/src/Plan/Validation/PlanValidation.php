<?php

declare(strict_types=1);

namespace SqlFixture\Plan\Validation;

use SqlFixture\Plan\PlanPrinter;
use SqlFixture\Plan\PlanStructureException;
use SqlFixture\Plan\Relation;

/**
 * Validates relation bindings and orders tables by dependency.
 *
 * @visibility root
 */
final class PlanValidation
{
    /**
     * A column set references one parent, so binding it twice is a mistake
     * whether the two relations agree or not.
     *
     * @param list<Relation> $relations
     * @throws PlanStructureException
     */
    public function rejectColumnsBoundTwice(array $relations): void
    {
        $seen = [];

        foreach ($relations as $relation) {
            $child = $relation->child();
            $key = $child->toString();

            if (isset($seen[$key])) {
                throw PlanStructureException::columnsBoundTwice($child, $seen[$key], $relation->parent());
            }

            $seen[$key] = $relation->parent();
        }
    }

    /**
     * A table that requires a row of itself can never finish.
     *
     * @param list<Relation> $relations
     * @throws PlanStructureException
     */
    public function rejectUnboundedSelfReferences(array $relations): void
    {
        foreach ($relations as $relation) {
            $isSelfReference = $relation->parent()->table === $relation->child()->table;

            if ($isSelfReference && $relation->minimumChildRows() > 0) {
                throw PlanStructureException::unboundedSelfReference(
                    $relation->parent()->table,
                    (new PlanPrinter())->printRelation($relation)
                );
            }
        }
    }

    /**
     * Order the tables so every parent comes before its children.
     *
     * Self references are left out of the ordering: a table cannot precede
     * itself, and an optional one terminates on its own.
     *
     * @param list<string> $tables
     * @param list<Relation> $relations
     * @return list<string>
     * @throws PlanStructureException
     */
    public function sortByDependency(array $tables, array $relations): array
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
     * @param list<Relation> $relations
     * @param list<string> $pending
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
