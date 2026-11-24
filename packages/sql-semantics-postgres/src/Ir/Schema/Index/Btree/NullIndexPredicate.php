<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\Schema\Index\Btree;

use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;

/**
 * A scalar NULL condition on a physical column of a partial index.
 *
 * @visibility public
 * @example Selecting rows whose second column is not NULL
 *     (new \SqlSemantics\Platform\PostgreSql\Ir\Schema\Index\Btree\NullIndexPredicate(1, true))->negated // => true
 */
final class NullIndexPredicate
{
    use Snapshot;

    /**
     * Fixes the physical slot and NULL condition polarity.
     */
    public function __construct(public readonly int $slot, public readonly bool $negated = false)
    {
        Check::input($slot >= 0, 'A partial index predicate needs a nonnegative column slot.');
    }
}
