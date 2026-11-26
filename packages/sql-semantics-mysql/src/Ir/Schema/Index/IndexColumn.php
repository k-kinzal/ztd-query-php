<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Ir\Schema\Index;

use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;

/**
 * A resolved signed field used as an ordered native index part.
 *
 * @visibility public
 * @example Selecting the second field in descending order
 *     (new \SqlSemantics\Platform\MySql\Ir\Schema\Index\IndexColumn(1, true))->slot // => 1
 */
final class IndexColumn
{
    use Snapshot;

    /**
     * Stores a slot in the detached owner heading and its effective direction.
     */
    public function __construct(public readonly int $slot, public readonly bool $descending = false)
    {
        Check::input($slot >= 0 && $slot < 1017, 'An index part needs a native column position from 0 through 1016.');
    }
}
