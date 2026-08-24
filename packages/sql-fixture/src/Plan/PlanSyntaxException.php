<?php

declare(strict_types=1);

namespace SqlFixture\Plan;

use InvalidArgumentException;

final class PlanSyntaxException extends InvalidArgumentException
{
    public static function emptyPlan(): self
    {
        return new self('A fixture plan must name at least one table.');
    }

    public static function emptyTableName(): self
    {
        return new self('A relation endpoint must name a table.');
    }

    public static function noColumns(string $table): self
    {
        return new self(sprintf('The endpoint for table %s names no columns.', $table));
    }

    public static function notATableName(string $part): self
    {
        return new self(sprintf(
            'A FixturePlan part must be a Relation or a plain table name, but "%s" is '
            . 'neither. To build a plan from relation syntax, use FixturePlan::from().',
            $part
        ));
    }

    public static function unbalancedBrackets(string $plan): self
    {
        return new self(sprintf(
            'The fixture plan closes a bracket it never opened. Plan: %s',
            $plan
        ));
    }

    public static function unexpected(string $plan, int $offset, string $expected): self
    {
        return new self(sprintf(
            'Cannot parse the fixture plan at offset %d: expected %s. Plan: %s',
            $offset,
            $expected,
            $plan
        ));
    }

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
