<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\Schema\Index\Btree;

use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;

/**
 * One covering value stored alongside the ordered B-tree keys.
 *
 * @visibility public
 * @example Including the third physical column as payload
 *     (new \SqlSemantics\Platform\PostgreSql\Ir\Schema\Index\Btree\IncludedColumn(2))->slot // => 2
 */
final class IncludedColumn
{
    use Snapshot;

    /**
     * Fixes the physical position of a covering value.
     */
    public function __construct(public readonly int $slot)
    {
        Check::input($slot >= 0, 'An included column needs a nonnegative column slot.');
    }
}
