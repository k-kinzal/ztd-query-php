<?php

declare(strict_types=1);

namespace SqlFixture\Fixture\Generation;

use SqlFixture\Plan\FixturePlan;

/**
 * Finds the table component joined by fixture relations.
 *
 * @visibility root
 */
final class ConnectedTables
{
    /**
     * Every table joined to this one by relations, however the arrows point.
     *
     * @return list<string>
     */
    public function connectedTo(FixturePlan $plan, string $table): array
    {
        $found = [$table];

        for ($index = 0; $index < count($found); $index++) {
            foreach ($plan->relations as $relation) {
                $ends = [$relation->left->table, $relation->right->table];

                foreach ([$ends, array_reverse($ends)] as [$from, $to]) {
                    if ($from === $found[$index] && !in_array($to, $found, true)) {
                        $found[] = $to;
                    }
                }
            }
        }

        return $found;
    }
}
