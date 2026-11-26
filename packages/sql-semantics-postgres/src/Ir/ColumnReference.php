<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir;

use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;

/**
 * A reference to a zero-based int4 slot within a visible occurrence.
 *
 * @visibility public
 * @example Referring to the first source column
 *     (new \SqlSemantics\Platform\PostgreSql\Ir\ColumnReference(0))->slot // => 0
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
