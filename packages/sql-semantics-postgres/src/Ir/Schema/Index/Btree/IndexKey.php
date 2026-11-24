<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\Schema\Index\Btree;

use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;

/**
 * One ordered occurrence of a physical table column in a B-tree key.
 *
 * @visibility public
 * @example Indexing the second table column in its native default order
 *     (new \SqlSemantics\Platform\PostgreSql\Ir\Schema\Index\Btree\IndexKey(1))->slot // => 1
 */
final class IndexKey
{
    use Snapshot;

    /**
     * Fixes the physical ordinal and effective ordering.
     */
    public function __construct(public readonly int $slot, public readonly IndexOrder $order = IndexOrder::AscendingNullsLast)
    {
        Check::input($slot >= 0, 'A B-tree key needs a nonnegative column slot.');
    }
}
