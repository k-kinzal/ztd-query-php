<?php

declare(strict_types=1);

namespace SqlSemantics\Serialization\Scalar;

use SqlSemantics\Model\Expression;
use SqlSemantics\Model\Scalar;
use SqlSemantics\Model\Sql\Tree;

/**
 * Routes language intrinsic operations whose operands have dedicated semantic roles.
 * @visibility SqlSemantics
 */
final class IntrinsicExpressions
{
    /**
     * Leaves ordinary operators, references, and invocations to the scalar serializer.
     */
    public static function write(Expression $value): ?Tree
    {
        return match (true) {
            $value instanceof Scalar\Text\Position => TextExpressions::write($value),
            $value instanceof Scalar\Temporal\DateShift,
            $value instanceof Scalar\Temporal\Extract => TemporalExpressions::write($value),
            $value instanceof Scalar\Control\RaiseError,
            $value instanceof Scalar\Control\RaiseIgnore => ControlExpressions::write($value),
            default => null,
        };
    }
}
