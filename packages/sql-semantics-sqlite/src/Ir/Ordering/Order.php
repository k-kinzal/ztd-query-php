<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Ir\Ordering;

use SqlSemantics\Platform\Sqlite\Model\Diagnostic\Check;
use SqlSemantics\Platform\Sqlite\Model\Statement\Snapshot;

/**
 * Orders by a one-based result position with effective direction and NULL placement.
 *
 * @visibility public
 * @example Ordering descending with SQLite's default NULL placement
 *     (new \SqlSemantics\Platform\Sqlite\Ir\Ordering\Order(1, true))->nullsFirst // => false
 */
final class Order
{
    use Snapshot;

    /**
     * Whether NULL precedes all non-NULL values for this term.
     */
    public readonly bool $nullsFirst;

    /**
     * Validates an output ordinal and fixes the effective NULL placement.
     */
    public function __construct(public readonly int $position, public readonly bool $descending = false, ?bool $nullsFirst = null)
    {
        Check::input($position >= 1, 'An output order position must be positive.');
        $this->nullsFirst = $nullsFirst ?? !$descending;
    }
}
