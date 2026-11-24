<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Ir\Schema\Trigger\Mutation;

use SqlSemantics\Platform\Sqlite\Model\Diagnostic\Check;
use SqlSemantics\Platform\Sqlite\Model\Statement\Snapshot;

/**
 * A resolved position of the current target row in a trigger mutation.
 *
 * @visibility public
 * @example Referring to the second local column
 *     (new \SqlSemantics\Platform\Sqlite\Ir\Schema\Trigger\Mutation\TriggerTargetField(1))->column // => 1
 */
final class TriggerTargetField
{
    use Snapshot;

    /**
     * Checks the ordinal before the enclosing action binds its table layout.
     */
    public function __construct(public readonly int $column)
    {
        Check::input($column >= 0, 'A local trigger field position cannot be negative.');
    }
}
