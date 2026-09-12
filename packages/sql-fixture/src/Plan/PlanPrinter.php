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
    /**
     * Formats .
     */
    public function print(FixturePlan $plan): string
    {
        $statements = [];

        foreach ((new Printing\RelationGroups())->group($plan->relations) as $group) {
            $statements[] = (new Printing\StatementPrinter())->printGroup($group);
        }

        foreach ((new Printing\PlanTables())->standaloneTables($plan) as $table) {
            $statements[] = $table;
        }

        return implode(', ', $statements);
    }

    /**
     * Write a single relation, without needing a plan to hold it.
     */
    public function printRelation(Relation $relation): string
    {
        return (new Printing\StatementPrinter())->printGroup([$relation]);
    }

}
