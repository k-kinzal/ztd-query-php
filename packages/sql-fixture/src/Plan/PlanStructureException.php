<?php

declare(strict_types=1);

namespace SqlFixture\Plan;

use RuntimeException;

/**
 * Reports a plan that reads correctly but could never be generated.
 *
 * The relations are each well-formed; it is what they say together that cannot
 * be satisfied — a column bound twice, or a group of tables each waiting on the
 * next. That is a property of the plan the caller wrote, so it is reported at
 * runtime alongside the failures of reading one.
 */
final class PlanStructureException extends RuntimeException
{
    /**
     * Reports a child column bound by two relations.
     *
     * Whichever relation ran second would overwrite the first, so the plan does
     * not say what the column holds.
     *
     * @param ColumnRef $child End both relations bind
     * @param ColumnRef $first Parent the first relation reads from
     * @param ColumnRef $second Parent the second relation reads from
     *
     * @return self Exception naming the column and both parents
     */
    public static function columnsBoundTwice(ColumnRef $child, ColumnRef $first, ColumnRef $second): self
    {
        return new self(sprintf(
            '%s is bound to %s and to %s. A column can reference one parent, so one of '
            . 'the two relations has to go.',
            $child->toString(),
            $first->toString(),
            $second->toString()
        ));
    }

    /**
     * Reports a group of tables each waiting on another.
     *
     * Every row carries values read off its parent, so a cycle has no row that
     * could be generated first.
     *
     * @param list<string> $cycle Tables still waiting on each other
     *
     * @return self Exception naming them
     */
    public static function cycle(array $cycle): self
    {
        return new self(sprintf(
            'The relations form a cycle: %s. Each table would have to be generated '
            . 'before itself, so there is no order that satisfies them.',
            implode(' -> ', [...$cycle, $cycle[0]])
        ));
    }

    /**
     * Reports a table required to reference a row of itself.
     *
     * Generating that row would require generating another first, and so on, so
     * the plan can never finish. Marking the reference optional is what makes a
     * self reference terminate.
     *
     * @param string $table Table that references itself
     * @param string $written The relation as the plan writes it
     *
     * @return self Exception naming the table and quoting the relation
     */
    public static function unboundedSelfReference(string $table, string $written): self
    {
        return new self(sprintf(
            'The relation %s makes every %s row need another one, without end. Mark the '
            . 'child optional with ? so the chain can stop.',
            $written,
            $table
        ));
    }
}
