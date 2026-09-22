<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Write\Storage;

use Override;

/**
 * A nested storage location with a mandatory writable base.
 *
 * @visibility public
 */
final class FieldPath implements Path
{
    /**

     */
    public function __construct(public readonly Path $base, public readonly string $field)
    {
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
        return \SqlSemantics\Type\TypeDescriptor::builtin($this->base->type()->dialect, 'unknown');
    }
}
