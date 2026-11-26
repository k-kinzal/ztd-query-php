<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Ir\Schema\Key;

use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;

/**
 * One resolved column position of a MySQL primary key.
 *
 * @visibility public
 * @example Selecting the second declared column
 *     (new \SqlSemantics\Platform\MySql\Ir\Schema\Key\PrimaryKeyColumn(1))->slot // => 1
 */
final class PrimaryKeyColumn
{
    use Snapshot;

    /**
     * Records the column position and its effective native index direction.
     */
    public function __construct(public readonly int $slot, public readonly bool $descending = false)
    {
        Check::input($slot >= 0 && $slot < 1017, 'A primary key needs a column slot from 0 through 1016.');
    }
}
