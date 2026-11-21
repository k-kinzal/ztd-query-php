<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Ir;

use SqlSemantics\Platform\Sqlite\Model\Diagnostic\Check;
use SqlSemantics\Platform\Sqlite\Model\Statement\Snapshot;

/**
 * A reference to a zero-based column slot of a local table occurrence.
 *
 * @visibility public
 * @example Referring to the first source column
 *     (new \SqlSemantics\Platform\Sqlite\Ir\ColumnReference(0))->slot // => 0
 */
final class ColumnReference
{
    use Snapshot;

    /**
     * Identifies a nonnegative position in a SELECT source.
     */
    public function __construct(public readonly int $slot, public readonly int $source = 0)
    {
        Check::input($slot >= 0, 'A column slot must be nonnegative.');
        Check::input($source >= 0, 'A table occurrence index must be nonnegative.');
    }
}
