<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Ir;

use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;

/**
 * A reference to a zero-based column slot of the SELECT's sole source.
 *
 * @visibility public
 * @example Referring to the first source column
 *     (new \SqlSemantics\Platform\MySql\Ir\ColumnReference(0))->slot // => 0
 */
final class ColumnReference
{
    use Snapshot;

    /**
     * Identifies a nonnegative position in a SELECT source.
     */
    public function __construct(public readonly int $slot)
    {
        Check::input($slot >= 0, 'A column slot must be nonnegative.');
    }
}
