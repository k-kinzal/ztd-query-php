<?php

declare(strict_types=1);

namespace SqlFixture\Plan;

/**
 * Writes a plan back out in the syntax PlanParser reads.
 *
 * Relations that share a left end, an operator and its optional markers are
 * printed as one grouped statement, which is how they would most likely have
 * been written by hand.
 */
final class PlanPrinter
{
    public function print(FixturePlan $plan): string
    {
        $statements = [];

        foreach ($this->group($plan->relations) as $group) {
            $statements[] = $this->printGroup($group);
        }

        foreach ($this->standaloneTables($plan) as $table) {
            $statements[] = $table;
        }

        return implode(', ', $statements);
    }

    /**
     * Tables the plan names without relating them to anything.
     *
     * They have to be written out too, or a plan that mentions one would not
     * read back as itself.
     *
     * @return list<string>
     */
    private function standaloneTables(FixturePlan $plan): array
    {
        $related = [];
        foreach ($plan->relations as $relation) {
            $related = [...$related, ...$relation->tables()];
        }

        $standalone = [];
        foreach ($plan->tables as $table) {
            if (!in_array($table, $related, true)) {
                $standalone[] = $table;
            }
        }

        return $standalone;
    }

    /**
     * Write a single relation, without needing a plan to hold it.
     */
    public function printRelation(Relation $relation): string
    {
        return $this->printGroup([$relation]);
    }

    /**
     * @param list<Relation> $relations
     * @return array<string, list<Relation>>
     */
    private function group(array $relations): array
    {
        $groups = [];

        foreach ($relations as $relation) {
            $key = $this->groupKey($relation);
            $groups[$key][] = $relation;
        }

        return $groups;
    }

    /**
     * Relations group when they would print the same left end and operator,
     * which is exactly when the shorthand can fold them together.
     */
    private function groupKey(Relation $relation): string
    {
        return $relation->left->toString() . ' ' . $this->operator($relation);
    }

    private function operator(Relation $relation): string
    {
        return ($relation->leftOptional ? '?' : '')
            . $relation->kind->value
            . ($relation->rightOptional ? '?' : '');
    }

    /**
     * @param list<Relation> $group
     */
    private function printGroup(array $group): string
    {
        $first = $group[0];
        $targets = array_map(
            static fn (Relation $relation): string => $relation->right->toString(),
            $group
        );

        $right = count($targets) === 1 ? $targets[0] : '[' . implode(', ', $targets) . ']';

        return $this->groupKey($first) . ' ' . $right;
    }
}
