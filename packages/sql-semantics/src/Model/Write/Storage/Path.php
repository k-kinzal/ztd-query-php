<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Write\Storage;

use SqlSemantics\Model\Scalar\Reference\ColumnReference;
use SqlSemantics\Model\Scalar\Reference\UnresolvedColumnReference;
use SqlSemantics\Type\TypeDescriptor;

/**
 * A writable column location; arbitrary computed expressions are not storage paths.
 *
 * @visibility public
 */
interface Path
{
    /**
     * Identifies the stored column at the root of this path.
     */
    public function column(): ColumnReference|UnresolvedColumnReference;

    /**
     * Returns the destination's type without loading its current value.
     */
    public function type(): TypeDescriptor;
}
