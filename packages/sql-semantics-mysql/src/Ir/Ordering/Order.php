<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Ir\Ordering;

use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;

/**
 * A checked native sort key on an output position.
 *
 * @visibility public
 * @example Reading the native NULL placement
 *     (new \SqlSemantics\Platform\MySql\Ir\Ordering\Order(1))->nullsFirst // => true
 */
final class Order
{
    use Snapshot;

    /**
     * Whether this key places NULL ahead of every non-NULL value.
     */
    public readonly bool $nullsFirst;

    /**
     * Fixes a one-based output slot, its direction and its effective placement of NULL.
     */
    public function __construct(public readonly int $position, public readonly bool $descending = false)
    {
        Check::input($position >= 1, 'An output order position must be positive.');
        $this->nullsFirst = !$descending;
    }
}
