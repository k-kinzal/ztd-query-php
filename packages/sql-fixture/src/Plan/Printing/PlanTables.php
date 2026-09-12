<?php

declare(strict_types=1);

namespace SqlFixture\Plan\Printing;

use SqlFixture\Plan\FixturePlan;

/**
 * Finds tables that do not participate in relations.
 *
 * @visibility root
 */
final class PlanTables
{
    /**
     * Tables the plan names without relating them to anything.
     *
     * They have to be written out too, or a plan that mentions one would not
     * read back as itself.
     *
     * @return list<string>
     */
    public function standaloneTables(FixturePlan $plan): array
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
}
