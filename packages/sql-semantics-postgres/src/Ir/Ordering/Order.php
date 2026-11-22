<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\Ordering;

use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;

/**
 * A checked native sort key on an output position.
 *
 * @visibility public
 * @example Reading the native NULL placement
 *     (new \SqlSemantics\Platform\PostgreSql\Ir\Ordering\Order(1))->nullsFirst // => false
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
    public function __construct(public readonly int $position, public readonly bool $descending = false, ?bool $nullsFirst = null)
    {
        Check::input($position >= 1, 'An output order position must be positive.');
        $this->nullsFirst = $nullsFirst ?? $descending;
    }
}
