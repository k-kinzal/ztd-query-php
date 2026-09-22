<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Scalar;

use SqlSemantics\Type\Nullability;
use SqlSemantics\Type\TypeDescriptor;

/**
 * Statically derived result facts, independent of the expression's operand shape.
 * @visibility SqlSemantics
 */
final class ExpressionFacts
{
    /**
     * @param list<string> $nullExtendedBy
     */
    public function __construct(public readonly TypeDescriptor $type, public readonly Nullability $nullability, public readonly array $nullExtendedBy = [])
    {
        \SqlSemantics\Model\Validation\Collections::strings($nullExtendedBy);
    }
}
