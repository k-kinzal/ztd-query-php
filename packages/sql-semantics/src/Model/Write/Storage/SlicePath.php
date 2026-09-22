<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Write\Storage;

use Override;

/**
 * A nested storage location with a mandatory writable base.
 *
 * @visibility public
 */
final class SlicePath implements Path
{
    /**
     * @throws \SqlSemantics\Model\Validation\InvalidStructure
     */
    public function __construct(public readonly Path $base, public readonly ?\SqlSemantics\Model\Expression $lower, public readonly ?\SqlSemantics\Model\Expression $upper)
    {
        foreach ([$lower, $upper] as $bound) {
            if ($bound !== null && $bound->type->dialect !== $base->type()->dialect) {
                throw new \SqlSemantics\Model\Validation\InvalidStructure('A storage slice and its bounds must use the same dialect.');
            }
        }
    }

    /**
     * Returns the column owning this storage location.
     */
    #[Override]
    public function column(): \SqlSemantics\Model\Scalar\Reference\ColumnReference|\SqlSemantics\Model\Scalar\Reference\UnresolvedColumnReference
    {
        return $this->base->column();
    }

    /**
     * Returns the declared destination type.
     */
    #[Override]
    public function type(): \SqlSemantics\Type\TypeDescriptor
    {
        return $this->base->type();
    }
}
