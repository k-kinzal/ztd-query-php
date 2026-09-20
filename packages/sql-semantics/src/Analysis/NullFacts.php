<?php

declare(strict_types=1);

namespace SqlSemantics\Analysis;

use SqlSemantics\Model\Expression;
use SqlSemantics\Type\Nullability;

/**
 * Conservative SQL NULL propagation, distinct from evaluating actual values.
 *
 * @visibility SqlSemantics
 */
final class NullFacts
{
    /**
     * @param list<Expression> $operands
     */
    public static function strict(array $operands): Nullability
    {
        $nullable = false;
        $unknown = false;
        foreach ($operands as $operand) {
            if ($operand->nullability === Nullability::AlwaysNull) {
                return Nullability::AlwaysNull;
            }
            $nullable = $nullable || $operand->nullability === Nullability::MaybeNull;
            $unknown = $unknown || $operand->nullability === Nullability::Unknown;
        }

        return $unknown ? Nullability::Unknown : ($nullable ? Nullability::MaybeNull : Nullability::NotNull);
    }

    /**
     * @param list<Expression> $operands
     */
    public static function coalesce(array $operands): Nullability
    {
        $allNull = true;
        $unknown = false;
        foreach ($operands as $operand) {
            if ($operand->nullability === Nullability::NotNull) {
                return Nullability::NotNull;
            }
            $allNull = $allNull && $operand->nullability === Nullability::AlwaysNull;
            $unknown = $unknown || $operand->nullability === Nullability::Unknown;
        }

        return $allNull ? Nullability::AlwaysNull : ($unknown ? Nullability::Unknown : Nullability::MaybeNull);
    }

    /**
     * @param list<Expression> $operands
     * @return list<string>
     */
    public static function extensions(array $operands, Nullability $nullability): array
    {
        if ($nullability === Nullability::NotNull) {
            return [];
        }
        $ids = [];
        foreach ($operands as $operand) {
            array_push($ids, ...$operand->nullExtendedBy);
        }

        return array_values(array_unique($ids));
    }
}
