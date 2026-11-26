<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Ir\From;

use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;

/**
 * A read from a native VARCHAR slot of a resolved physical input occurrence.
 *
 * @visibility public
 * @example Reading the third position of the second input
 *     (new \SqlSemantics\Platform\MySql\Ir\From\TextColumnReference(2, 1))->occurrence // => 1
 */
final class TextColumnReference
{
    use Snapshot;

    /**
     * Fixes the nonnegative slot and occurrence checked at the owning SELECT.
     */
    public function __construct(public readonly int $slot, public readonly int $occurrence = 0)
    {
        Check::input($slot >= 0, 'A text column slot must be nonnegative.');
        Check::input($occurrence >= 0, 'A text column occurrence must be nonnegative.');
    }
}
