<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Ir\From;

use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;

/**
 * A resolved read whose visible input type is an explicit signed integral width.
 *
 * @visibility public
 * @example Reading the first slot of a joined occurrence
 *     (new \SqlSemantics\Platform\MySql\Ir\From\IntegralColumnReference(0, 1))->occurrence // => 1
 */
final class IntegralColumnReference
{
    use Snapshot;

    /**
     * Identifies an input occurrence and a slot within its declared heading.
     */
    public function __construct(public readonly int $slot, public readonly int $occurrence = 0)
    {
        Check::input($slot >= 0 && $occurrence >= 0, 'An integral reference needs a nonnegative slot and occurrence.');
    }
}
