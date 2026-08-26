<?php

declare(strict_types=1);

namespace SqlFixture\Plan;

use RuntimeException;

/**
 * Reports a plan that could not be read.
 *
 * A plan arrives as text a test author wrote, so failing to read one is a fact
 * about that text rather than a mistake in the code reading it. It is reported
 * at runtime, and a caller reading plans from a file or a fixture attribute can
 * catch it.
 */
final class PlanSyntaxException extends RuntimeException
{
    /**
     * Reports a plan that names nothing at all.
     *
     * @return self Exception saying the plan is empty
     */
    public static function emptyPlan(): self
    {
        return new self('A fixture plan must name at least one table.');
    }

    /**
     * Reports an endpoint written without a table.
     *
     * @return self Exception saying the table name is empty
     */
    public static function emptyTableName(): self
    {
        return new self('A relation endpoint must name a table.');
    }

    /**
     * Reports an endpoint that names a table and no column of it.
     *
     * @param string $table Table the endpoint named
     *
     * @return self Exception naming the table
     */
    public static function noColumns(string $table): self
    {
        return new self(sprintf('The endpoint for table %s names no columns.', $table));
    }

    /**
     * Reports text written where a bare table name was expected.
     *
     * A relation missing its dot reads as a table name, so refusing anything
     * that is not one is what turns that typo into an error the author can see.
     *
     * @param string $part Text as the plan wrote it
     *
     * @return self Exception quoting the text
     */
    public static function notATableName(string $part): self
    {
        return new self(sprintf(
            'A FixturePlan part must be a Relation or a plain table name, but "%s" is '
            . 'neither. To build a plan from relation syntax, use FixturePlan::from().',
            $part
        ));
    }

    /**
     * Reports a bracket closed that was never opened.
     *
     * @param string $plan Plan as it was written
     *
     * @return self Exception quoting the plan
     */
    public static function unbalancedBrackets(string $plan): self
    {
        return new self(sprintf(
            'The fixture plan closes a bracket it never opened. Plan: %s',
            $plan
        ));
    }

    /**
     * Reports what was written where something else was expected.
     *
     * @param string $plan Plan as it was written
     * @param int $offset Where in the plan the walk had got to
     * @param string $expected What was expected there
     *
     * @return self Exception naming the place and what was wanted
     */
    public static function unexpected(string $plan, int $offset, string $expected): self
    {
        return new self(sprintf(
            'Cannot parse the fixture plan at offset %d: expected %s. Plan: %s',
            $offset,
            $expected,
            $plan
        ));
    }

    /**
     * Reports a many-to-many relation, which a fixture cannot generate.
     *
     * DBML writes one as `<>`, and it says two tables relate without saying
     * through what, so there is no row a fixture could put between them.
     *
     * @param string $plan Plan as it was written
     *
     * @return self Exception quoting the plan
     */
    public static function manyToManyUnsupported(string $plan): self
    {
        return new self(sprintf(
            'The <> operator is not supported, because a fixture has to put rows in the '
            . 'junction table and so must name it. Write the two halves instead, for '
            . 'example "order.id < order_detail.order_id, order_detail.product_id > '
            . 'product.id". Plan: %s',
            $plan
        ));
    }

    /**
     * Reports two ends of a relation that name different numbers of columns.
     *
     * A composite relation lines its columns up one for one, so ends of
     * different widths cannot be lined up at all.
     *
     * @param ColumnRef $left End written on the left
     * @param ColumnRef $right End written on the right
     *
     * @return self Exception naming both ends
     */
    public static function compositeArityMismatch(ColumnRef $left, ColumnRef $right): self
    {
        return new self(sprintf(
            'The relation %s ... %s names %d columns on one side and %d on the other.',
            $left->toString(),
            $right->toString(),
            count($left->columns),
            count($right->columns)
        ));
    }
}
