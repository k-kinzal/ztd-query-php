<?php

declare(strict_types=1);

namespace SqlFixture\Plan;

/**
 * Refuses the plans that could be written but could never be generated.
 *
 * Two mistakes are worth catching where the plan is built rather than where it
 * is walked. A column bound by two relations would be given two values, and
 * whichever was written second would silently win. A table required to
 * reference a row of itself can never be finished, because generating that row
 * requires generating another first.
 */
final class PlanIntegrity
{
    /**
     * Refuses a plan that binds the same child column twice.
     *
     * @param list<Relation> $relations Relations the plan declares
     *
     * @throws PlanStructureException When two relations bind the same column
     */
    public function assertColumnsBoundOnce(array $relations): void
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
     * Refuses a table that must reference a row of itself.
     *
     * Marking the reference optional is what makes a self reference finish, so
     * only a required one is refused.
     *
     * @param list<Relation> $relations Relations the plan declares
     *
     * @throws PlanStructureException When a required relation joins a table to itself
     */
    public function assertNoUnboundedSelfReference(array $relations): void
    {
        foreach ($relations as $relation) {
            if ($relation->parent()->table !== $relation->child()->table) {
                continue;
            }
            if ($relation->minimumChildRows() > 0) {
                throw PlanStructureException::unboundedSelfReference(
                    $relation->parent()->table,
                    (new PlanPrinter())->printRelation($relation),
                );
            }
        }
    }
}
