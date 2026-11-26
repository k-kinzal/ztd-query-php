<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Ir;

use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;

/**
 * A reference to a zero-based slot within a visible table occurrence.
 *
 * @visibility public
 * @example Referring to the first source column
 *     (new \SqlSemantics\Platform\MySql\Ir\ColumnReference(0))->slot // => 0
 */
final class ColumnReference
{
    use Snapshot;

    /**
     * Identifies a nonnegative column and occurrence position in a source.
     */
    public function __construct(public readonly int $slot, public readonly int $occurrence = 0)
    {
        Check::input($slot >= 0, 'A column slot must be nonnegative.');
        Check::input($occurrence >= 0, 'A table occurrence must be nonnegative.');
    }
}
