<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Write\Storage;

use Override;

/**
 * A column location.
 *
 * @visibility public
 */
final class ColumnPath implements Path
{
    /**

     */
    public function __construct(public readonly \SqlSemantics\Model\Scalar\Reference\ColumnReference|\SqlSemantics\Model\Scalar\Reference\UnresolvedColumnReference $reference)
    {
    }

    /**
     * Returns the column owning this storage location.
     */
    #[Override]
    public function column(): \SqlSemantics\Model\Scalar\Reference\ColumnReference|\SqlSemantics\Model\Scalar\Reference\UnresolvedColumnReference
    {
        return $this->reference;
    }

    /**
     * Returns the declared destination type.
     */
    #[Override]
    public function type(): \SqlSemantics\Type\TypeDescriptor
    {
        return $this->reference->type;
    }
}
