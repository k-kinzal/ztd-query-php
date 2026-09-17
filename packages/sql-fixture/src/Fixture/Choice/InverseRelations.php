<?php

declare(strict_types=1);

namespace SqlFixture\Fixture\Choice;

use SqlFixture\Plan\FixturePlan;
use SqlFixture\Plan\Relation;

/**
 * Visits equivalent inverse choice edges once, then selects per generated row.
 * @visibility root
 */
final class InverseRelations
{
    /**
     * @param list<Relation> $relations
     * @return list<Relation>
     */
    public function unique(FixturePlan $plan, array $relations): array
    {
        $seen = [];
        $result = [];
        foreach ($relations as $relation) {
            $key = $this->key($plan, $relation);
            if ($key !== null && in_array($key, $seen, true)) {
                continue;
            }
            $seen[] = $key;
            $result[] = $relation;
        }
        return $result;
    }

    /**
     * Distinguishes unrelated choices and unconditional edges.
     */
    public function key(FixturePlan $plan, Relation $relation): ?string
    {
        foreach ($plan->choices as $index => $choice) {
            if (in_array($relation, $choice->relations(), true)) {
                return $index . ':' . $relation->parent()->toString() . ':' . $relation->child()->toString() . ':' . $relation->kind->value;
            }
        }
        return null;
    }
}
