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
        if ($plan->relations === []) {
            return implode(', ', $plan->tables);
        }

        $statements = [];
        foreach ($this->group($plan->relations) as $group) {
            $statements[] = $this->printGroup($group);
        }

        return implode(', ', $statements);
    }

    /**
     * @param list<Relation> $relations
     * @return list<list<Relation>>
     */
    private function group(array $relations): array
    {
        $groups = [];

        foreach ($relations as $relation) {
            $key = $this->groupKey($relation);
            $groups[$key][] = $relation;
        }

        return array_values($groups);
    }

    private function groupKey(Relation $relation): string
    {
        return implode('|', [
            $relation->left->toString(),
            $relation->kind->value,
            $relation->leftOptional ? '?' : '',
            $relation->rightOptional ? '?' : '',
        ]);
    }

    /**
     * @param list<Relation> $group
     */
    private function printGroup(array $group): string
    {
        $first = $group[0];

        $operator = ($first->leftOptional ? '?' : '')
            . $first->kind->value
            . ($first->rightOptional ? '?' : '');

        $targets = array_map(
            static fn (Relation $relation): string => $relation->right->toString(),
            $group
        );

        $right = count($targets) === 1 ? $targets[0] : '[' . implode(', ', $targets) . ']';

        return $first->left->toString() . ' ' . $operator . ' ' . $right;
    }
}
