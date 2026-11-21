<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir;

use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;

/**
 * A reference to a zero-based column slot of the SELECT's sole source.
 *
 * @visibility public
 * @example Referring to the first source column
 *     (new \SqlSemantics\Platform\PostgreSql\Ir\ColumnReference(0))->slot // => 0
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
