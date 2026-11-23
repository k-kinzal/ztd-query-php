<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\From;

use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;

/**
 * A native signed 64-bit field identified by its position in a visible occurrence.
 *
 * @visibility public
 * @example Reading the third field of the second input
 *     (new \SqlSemantics\Platform\PostgreSql\Ir\From\WideIntegerColumnReference(2, 1))->occurrence // => 1
 */
final class WideIntegerColumnReference
{
    use Snapshot;

    /**
     * Identifies the field and occurrence positions.
     */
    public function __construct(public readonly int $slot, public readonly int $occurrence = 0)
    {
        Check::input($slot >= 0, 'A column slot must be nonnegative.');
        Check::input($occurrence >= 0, 'A table occurrence must be nonnegative.');
    }
}
