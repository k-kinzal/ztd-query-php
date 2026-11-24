<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\Schema\Index\Btree;

use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;

/**
 * A resolved physical Boolean field read as a nullable index predicate value.
 *
 * @visibility public
 * @example Referencing the second physical Boolean field of an index owner
 *     (new \SqlSemantics\Platform\PostgreSql\Ir\Schema\Index\Btree\IndexBooleanColumn(1))->slot // => 1
 */
final class IndexBooleanColumn
{
    use Snapshot;

    /**
     * Fixes the physical slot of the Boolean value.
     */
    public function __construct(public readonly int $slot)
    {
        Check::input($slot >= 0, 'An index Boolean field needs a nonnegative column slot.');
    }
}
