<?php

declare(strict_types=1);

namespace SqlFixture\Plan\Printing;

use SqlFixture\Plan\Relation;

/**
 * Renders a group of relations in DBML syntax.
 *
 * @visibility root
 */
final class StatementPrinter
{
    /**
     * @param list<Relation> $group
     */
    public function printGroup(array $group): string
    {
        $first = $group[0];
        $targets = array_map(
            static fn (Relation $relation): string => $relation->right->toString(),
            $group
        );

        $right = count($targets) === 1 ? $targets[0] : '[' . implode(', ', $targets) . ']';

        return (new RelationGroups())->groupKey($first) . ' ' . $right;
    }
}
