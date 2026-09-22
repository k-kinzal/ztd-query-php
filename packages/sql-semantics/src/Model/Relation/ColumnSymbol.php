<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Relation;

use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\Type\Nullability;
use SqlSemantics\Type\TypeDescriptor;

/**
 * A column's declared identity and type, without a stored or computed value.
 *
 * @visibility public
 */
final class ColumnSymbol
{
    /**
     * @throws InvalidStructure
     */
    public function __construct(
        public readonly int $ordinal,
        public readonly string $name,
        public readonly TypeDescriptor $type,
        public readonly Nullability $nullability,
    ) {
        if ($ordinal < 0) {
            throw new InvalidStructure('A column symbol requires a name and a nonnegative position.');
        }
    }
}
