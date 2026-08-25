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
     * @param list<string> $cycle
     */
    public static function cycle(array $cycle): self
    {
        return new self(sprintf(
            'The relations form a cycle: %s. Each table would have to be generated '
            . 'before itself, so there is no order that satisfies them.',
            implode(' -> ', [...$cycle, $cycle[0]])
        ));
    }

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
