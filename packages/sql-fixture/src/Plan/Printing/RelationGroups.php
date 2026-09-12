<?php

declare(strict_types=1);

namespace SqlFixture\Plan\Printing;

use SqlFixture\Plan\Relation;

/**
 * Groups relations by their printable left endpoint and operator.
 *
 * @visibility root
 */
final class RelationGroups
{
    /**
     * @param list<Relation> $relations
     * @return array<string, list<Relation>>
     */
    public function group(array $relations): array
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
    public function groupKey(Relation $relation): string
    {
        return $relation->left->toString() . ' ' . $this->operator($relation);
    }

    /**
     * Formats the relation operator with its optional endpoint markers.
     */
    public function operator(Relation $relation): string
    {
        return ($relation->leftOptional ? '?' : '')
            . $relation->kind->value
            . ($relation->rightOptional ? '?' : '');
    }
}
