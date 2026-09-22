<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Scalar\Conditional;

use SqlSemantics\Model\Expression;
use SqlSemantics\Model\Scalar\Value\RowExpression;
use SqlSemantics\Type\Identity\BuiltinIdentity;
use SqlSemantics\Type\Identity\NamedIdentity;
use SqlSemantics\Type\Nullability;

/**
 * Distinguishes a non-NULL row object from nullable comparisons of its fields.
 * @visibility SqlSemantics
 */
final class ComparisonNullability
{
    /**
     * Describes whether field comparisons can return NULL, without comparing values.
     */
    public static function of(Expression $value): Nullability
    {
        if ($value instanceof RowExpression) {
            foreach ($value->items as $field) {
                if (self::of($field) !== Nullability::NotNull) {
                    return Nullability::MaybeNull;
                }
            }
            return Nullability::NotNull;
        }
        if ($value->type->identity === BuiltinIdentity::Record || $value->type->identity instanceof NamedIdentity) {
            return Nullability::Unknown;
        }
        return $value->nullability;
    }
}
