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
     * Writes a plan in the syntax it can be read back from.
     *
     * Relations sharing a left end and operator fold into one bracketed line, and
     * tables no relation touches are written on their own, so a plan printed and
     * read back is the plan that was printed.
     *
     * @param FixturePlan $plan Plan to write
     *
     * @return string The plan
     */
    public function print(FixturePlan $plan): string
    {
        $statements = [];

        foreach ($this->group($plan->relations) as $group) {
            if ($group !== []) {
                $statements[] = $this->printGroup($group);
            }
        }

        foreach ($this->standaloneTables($plan) as $table) {
            $statements[] = $table;
        }

        return implode(', ', $statements);
    }

    /**
     * Answers the tables a plan names without relating them to anything.
     *
     * They have to be written out too, or a plan that mentions one would not read
     * back as itself.
     *
     * @param FixturePlan $plan Plan being written
     *
     * @return list<string> Tables no relation touches
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

    /**
     * Write a single relation, without needing a plan to hold it.
     */
    public function printRelation(Relation $relation): string
    {
        return $this->printGroup([$relation]);
    }

    /**
     * Gathers the relations that can be written as one line.
     *
     * @param list<Relation> $relations Relations to gather
     *
     * @return array<string, list<Relation>> Left end and operator => the relations sharing them
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
     * Answers what two relations must share to be written together.
     *
     * Relations group when they would print the same left end and operator, which
     * is exactly when the shorthand can fold them into one line.
     *
     * @param Relation $relation Relation to key
     *
     * @return string Its left end and operator
     */
    public function groupKey(Relation $relation): string
    {
        return $relation->left->toString() . ' ' . $this->operator($relation);
    }

    /**
     * Writes the operator of a relation, with the optional markers it carries.
     *
     * @param Relation $relation Relation to write
     *
     * @return string The operator as the plan syntax spells it
     */
    public function operator(Relation $relation): string
    {
        return ($relation->leftOptional ? '?' : '')
            . $relation->kind->value
            . ($relation->rightOptional ? '?' : '');
    }

    /**
     * Writes one line, folding several targets into a bracketed group.
     *
     * @param non-empty-list<Relation> $group Relations sharing a left end and operator
     *
     * @return string The line they are written as
     */
    public function printGroup(array $group): string
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
