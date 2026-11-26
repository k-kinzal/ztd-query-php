<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Ir\Rows;

use SqlSemantics\Platform\MySql\Ir\Arithmetic;
use SqlSemantics\Platform\MySql\Ir\Binding\Parameter;
use SqlSemantics\Platform\MySql\Ir\Binding\ParameterTarget;
use SqlSemantics\Platform\MySql\Ir\BooleanLiteral;
use SqlSemantics\Platform\MySql\Ir\ColumnReference;
use SqlSemantics\Platform\MySql\Ir\Expression\Choice\SignedCase;
use SqlSemantics\Platform\MySql\Ir\Expression\CollatedText;
use SqlSemantics\Platform\MySql\Ir\Expression\SignedCast;
use SqlSemantics\Platform\MySql\Ir\Expression\UnsignedCast;
use SqlSemantics\Platform\MySql\Ir\From\IntegralColumnReference;
use SqlSemantics\Platform\MySql\Ir\From\TextColumnReference;
use SqlSemantics\Platform\MySql\Ir\IntegerLiteral;
use SqlSemantics\Platform\MySql\Ir\Literal\ProjectedIntegerLiteral;
use SqlSemantics\Platform\MySql\Ir\Literal\TextLiteral;
use SqlSemantics\Platform\MySql\Ir\NullLiteral;
use SqlSemantics\Platform\MySql\Ir\Predicate\IntegerComparison;
use SqlSemantics\Platform\MySql\Ir\Predicate\IntegerMembership;
use SqlSemantics\Platform\MySql\Ir\Predicate\IntegerRange;
use SqlSemantics\Platform\MySql\Ir\Predicate\NullTest;
use SqlSemantics\Platform\MySql\Ir\Predicate\Text\TextComparison;
use SqlSemantics\Platform\MySql\Ir\Predicate\Truth\TruthTest;
use SqlSemantics\Platform\MySql\Ir\Schema\IntegerStorage;
use SqlSemantics\Platform\MySql\Ir\Select;
use SqlSemantics\Platform\MySql\Model\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Model\Diagnostic\InvalidConstruction;

/**
 * Derives the checked native type and grouping of a compound.
 *
 * @visibility SqlSemantics\Platform\MySql
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
     * @throws InvalidConstruction When a standalone field has no checked set role
     */
    public static function type(Select|Compound $query, int $position): ResultKind
    {
        if ($query instanceof Compound) {
            return self::merge(self::type($query->left, $position), self::type($query->right, $position), self::legacy($query));
        }
        $column = $query->columns[$position]->expression;

        return match (true) {
            $column instanceof TextLiteral, $column instanceof TextColumnReference => ResultKind::Text,
            $column instanceof CollatedText => ResultKind::Text,
            $column instanceof UnsignedCast => ResultKind::UnsignedInteger,
            $column instanceof Parameter && $column->target === ParameterTarget::Unsigned => ResultKind::UnsignedInteger,
            $column instanceof NullLiteral => ResultKind::Unknown,
            $column instanceof SignedCase => match ($query->signedCaseLayout($position)['storage'] ?? null) {
                null => ResultKind::Unknown,
                IntegerStorage::Wide => ResultKind::WideInteger,
                IntegerStorage::Tiny, IntegerStorage::Small, IntegerStorage::Medium, IntegerStorage::Standard => ResultKind::Integer,
            },
            $column instanceof ColumnReference => ResultKind::Integer,
            $column instanceof IntegralColumnReference => throw new InvalidConstruction('An extended integral field has no checked set column role.'),
            $column instanceof Arithmetic, $column instanceof BooleanLiteral, $column instanceof IntegerLiteral, $column instanceof IntegerComparison, $column instanceof IntegerMembership, $column instanceof IntegerRange, $column instanceof NullTest, $column instanceof ProjectedIntegerLiteral, $column instanceof TruthTest, $column instanceof TextComparison, $column instanceof Parameter, $column instanceof SignedCast => ResultKind::WideInteger,
        };
    }

    /**
     * Combines the supported native type roles at a set node.
     */
    public static function merge(ResultKind $left, ResultKind $right, bool $legacy): ResultKind
    {
        if ($left === ResultKind::Text || $right === ResultKind::Text) {
            return ResultKind::Text;
        }
        if ($left === ResultKind::DecimalInteger || $right === ResultKind::DecimalInteger) {
            return ResultKind::DecimalInteger;
        }
        if ($left === ResultKind::UnsignedInteger || $right === ResultKind::UnsignedInteger) {
            if ($legacy) {
                return $left === ResultKind::UnsignedInteger ? ResultKind::UnsignedInteger : ResultKind::WideInteger;
            }
            $other = $left === ResultKind::UnsignedInteger ? $right : $left;

            return match ($other) {
                ResultKind::Unknown, ResultKind::NullOnly, ResultKind::UnsignedInteger => ResultKind::UnsignedInteger,
                ResultKind::Integer, ResultKind::WideInteger => ResultKind::DecimalInteger,
            };
        }
        if ($left === ResultKind::WideInteger || $right === ResultKind::WideInteger) {
            return ResultKind::WideInteger;
        }

        return $left === ResultKind::Integer || $right === ResultKind::Integer ? ResultKind::Integer : ResultKind::NullOnly;
    }

    /**
     * Identifies a release whose UNION grammar uses an ordinary left spine.
     */
    public static function legacy(Select|Compound $query): bool
    {
        if ($query instanceof Compound) {
            return self::legacy($query->left);
        }

        return $query->release === GrammarRelease::MySql5651 || $query->release === GrammarRelease::MySql5744;
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
