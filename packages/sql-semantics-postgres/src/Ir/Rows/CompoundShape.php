<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\Rows;

use SqlSemantics\Platform\PostgreSql\Ir\Arithmetic;
use SqlSemantics\Platform\PostgreSql\Ir\BooleanLiteral;
use SqlSemantics\Platform\PostgreSql\Ir\ColumnReference;
use SqlSemantics\Platform\PostgreSql\Ir\IntegerLiteral;
use SqlSemantics\Platform\PostgreSql\Ir\NullLiteral;
use SqlSemantics\Platform\PostgreSql\Ir\Select;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;

/**
 * Derives the checked native type and grouping of a compound.
 *
 * @visibility SqlSemantics\Platform\PostgreSql
 */
final class CompoundShape
{
    /**
     * Counts the positions fixed by the leftmost projection.
     */
    public static function width(Select|Compound $query): int
    {
        return $query instanceof Compound ? self::width($query->left) : count($query->columns);
    }

    /**
     * Counts set operators on the longest path to a leaf.
     */
    public static function depth(Select|Compound $query): int
    {
        if ($query instanceof Select) {
            return 0;
        }
        $left = self::depth($query->left);
        $right = self::depth($query->right);

        return 1 + ($left > $right ? $left : $right);
    }

    /**
     * Counts query leaves as they occur in the expanded SQL.
     */
    public static function arms(Select|Compound $query): int
    {
        return $query instanceof Compound ? self::arms($query->left) + self::arms($query->right) : 1;
    }

    /**
     * Derives the native type for a checked output position.
     */
    public static function type(Select|Compound $query, int $position): ResultType
    {
        if ($query instanceof Compound) {
            return self::merge(self::type($query->left, $position), self::type($query->right, $position));
        }
        $column = $query->columns[$position]->expression;

        return match (true) {
            $column instanceof NullLiteral => ResultType::Unknown,
            $column instanceof BooleanLiteral => ResultType::Boolean,
            $column instanceof Arithmetic, $column instanceof ColumnReference, $column instanceof IntegerLiteral => ResultType::Integer,
        };
    }

    /**
     * Combines the supported native type roles at a set node.
     */
    public static function merge(ResultType $left, ResultType $right): ResultType
    {
        if ($left === ResultType::Unknown) {
            return $right === ResultType::Unknown ? ResultType::Text : $right;
        }
        Check::input($right === ResultType::Unknown || $left === $right, 'PostgreSQL compound output types are incompatible.');

        return $left;
    }

    /**
     * Adds parentheses where a child is itself a compound.
     */
    public static function sql(Select|Compound $query): string
    {
        $sql = $query->toString();

        return $query instanceof Compound ? '(' . $sql . ')' : $sql;
    }
}
