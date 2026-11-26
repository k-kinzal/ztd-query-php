<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\Rows;

use SqlSemantics\Platform\PostgreSql\Ir\Arithmetic;
use SqlSemantics\Platform\PostgreSql\Ir\Binding\Parameter;
use SqlSemantics\Platform\PostgreSql\Ir\BooleanLiteral;
use SqlSemantics\Platform\PostgreSql\Ir\ColumnReference;
use SqlSemantics\Platform\PostgreSql\Ir\Expression\BuiltinCast;
use SqlSemantics\Platform\PostgreSql\Ir\Expression\CastDestination;
use SqlSemantics\Platform\PostgreSql\Ir\Expression\CollatedText;
use SqlSemantics\Platform\PostgreSql\Ir\Expression\IntegerCase;
use SqlSemantics\Platform\PostgreSql\Ir\Expression\TextLiteral;
use SqlSemantics\Platform\PostgreSql\Ir\Expression\WideIntegerLiteral;
use SqlSemantics\Platform\PostgreSql\Ir\From\BooleanColumnReference;
use SqlSemantics\Platform\PostgreSql\Ir\From\ColumnKind;
use SqlSemantics\Platform\PostgreSql\Ir\From\TextColumnReference;
use SqlSemantics\Platform\PostgreSql\Ir\From\WideIntegerColumnReference;
use SqlSemantics\Platform\PostgreSql\Ir\IntegerLiteral;
use SqlSemantics\Platform\PostgreSql\Ir\NullLiteral;
use SqlSemantics\Platform\PostgreSql\Ir\Predicate\IntegerComparison;
use SqlSemantics\Platform\PostgreSql\Ir\Predicate\IntegerDistinctness;
use SqlSemantics\Platform\PostgreSql\Ir\Predicate\IntegerMembership;
use SqlSemantics\Platform\PostgreSql\Ir\Predicate\IntegerRange;
use SqlSemantics\Platform\PostgreSql\Ir\Predicate\NullTest;
use SqlSemantics\Platform\PostgreSql\Ir\Predicate\Text\TextComparison;
use SqlSemantics\Platform\PostgreSql\Ir\Predicate\TruthTest;
use SqlSemantics\Platform\PostgreSql\Ir\Rows\Values\ValuesQuery;
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
    public static function width(Select|ValuesQuery|Compound $query): int
    {
        return match (true) {
            $query instanceof Compound => self::width($query->left),
            $query instanceof ValuesQuery => count($query->rows[0]->cells),
            $query instanceof Select => count($query->columns),
        };
    }

    /**
     * Counts set operators on the longest path to a leaf.
     */
    public static function depth(Select|ValuesQuery|Compound $query): int
    {
        if (!$query instanceof Compound) {
            return 0;
        }
        $left = self::depth($query->left);
        $right = self::depth($query->right);

        return 1 + ($left > $right ? $left : $right);
    }

    /**
     * Counts query leaves as they occur in the expanded SQL.
     */
    public static function arms(Select|ValuesQuery|Compound $query): int
    {
        return $query instanceof Compound ? self::arms($query->left) + self::arms($query->right) : 1;
    }

    /**
     * Derives the native type for a checked output position.
     */
    public static function type(Select|ValuesQuery|Compound $query, int $position): ResultKind
    {
        if ($query instanceof Compound) {
            return self::merge(self::type($query->left, $position), self::type($query->right, $position));
        }
        if ($query instanceof ValuesQuery) {
            return match ($query->columnKind($position)) {
                ColumnKind::Int4 => ResultKind::Integer,
                ColumnKind::Int8 => ResultKind::WideInteger,
                ColumnKind::Boolean => ResultKind::Boolean,
                ColumnKind::Text => ResultKind::Text,
            };
        }
        $column = $query->columns[$position]->expression;

        return match (true) {
            $column instanceof BuiltinCast, $column instanceof Parameter => match ($column instanceof BuiltinCast ? $column->target : $column->input) {
                CastDestination::Integer => ResultKind::Integer,
                CastDestination::WideInteger => ResultKind::WideInteger,
                CastDestination::Boolean => ResultKind::Boolean,
                CastDestination::Text => ResultKind::Text,
            },
            $column instanceof WideIntegerLiteral, $column instanceof WideIntegerColumnReference => ResultKind::WideInteger,
            $column instanceof TextLiteral, $column instanceof TextColumnReference, $column instanceof CollatedText => ResultKind::Text,
            $column instanceof NullLiteral => ResultKind::Unknown,
            $column instanceof BooleanLiteral, $column instanceof BooleanColumnReference, $column instanceof IntegerComparison, $column instanceof IntegerRange, $column instanceof IntegerDistinctness, $column instanceof IntegerMembership, $column instanceof NullTest, $column instanceof TruthTest, $column instanceof TextComparison => ResultKind::Boolean,
            $column instanceof Arithmetic, $column instanceof ColumnReference, $column instanceof IntegerCase, $column instanceof IntegerLiteral => ResultKind::Integer,
        };
    }

    /**
     * Combines the supported native type roles at a set node.
     */
    public static function merge(ResultKind $left, ResultKind $right): ResultKind
    {
        if ($left === ResultKind::Unknown) {
            return $right === ResultKind::Unknown ? ResultKind::Text : $right;
        }
        if (($left === ResultKind::Integer && $right === ResultKind::WideInteger)
            || ($left === ResultKind::WideInteger && $right === ResultKind::Integer)) {
            return ResultKind::WideInteger;
        }
        Check::input($right === ResultKind::Unknown || $left === $right, 'PostgreSQL compound output types are incompatible.');

        return $left;
    }

    /**
     * Adds parentheses where a child is itself a compound.
     */
    public static function sql(Select|ValuesQuery|Compound $query): string
    {
        $sql = $query->toString();

        return $query instanceof Compound ? '(' . $sql . ')' : $sql;
    }
}
