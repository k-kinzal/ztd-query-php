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
        return SqlJsonExpressions::write($value) ?? \SqlSemantics\Serialization\Document\XmlExpressions::write($value) ?? match (true) {
            $value instanceof Scalar\Text\Position,
            $value instanceof Scalar\Text\Trim,
            $value instanceof Scalar\Text\Normalization,
            $value instanceof Scalar\Text\NormalizedPredicate,
            $value instanceof Scalar\Text\FullTextSearch => TextExpressions::write($value),
            $value instanceof Scalar\Document\JsonScalarExtraction => DocumentExpressions::write($value),
            $value instanceof Scalar\Temporal\DateShift,
            $value instanceof Scalar\Temporal\Extract,
            $value instanceof Scalar\Temporal\PeriodOverlap,
            $value instanceof Scalar\Temporal\ZoneConversion,
            $value instanceof Scalar\Temporal\TemporalFormat => TemporalExpressions::write($value),
            $value instanceof Scalar\Control\RaiseError,
            $value instanceof Scalar\Control\RaiseIgnore => ControlExpressions::write($value),
            default => null,
        };
    }
}
