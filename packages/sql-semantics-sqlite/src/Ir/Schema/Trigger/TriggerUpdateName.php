<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Ir\Schema\Trigger;

use SqlSemantics\Platform\Sqlite\Ir\Identifier;
use SqlSemantics\Platform\Sqlite\Model\Diagnostic\Check;
use SqlSemantics\Platform\Sqlite\Model\Diagnostic\InvalidConstruction;
use SqlSemantics\Platform\Sqlite\Model\Statement\Snapshot;

/**
 * A resolved UPDATE OF position or a known absent declared name.
 *
 * @visibility public
 * @example Selecting changes to the second watched column
 *     (new \SqlSemantics\Platform\Sqlite\Ir\Schema\Trigger\TriggerUpdateName(1))->selector // => 1
 */
final class TriggerUpdateName
{
    use Snapshot;

    /**
     * Fixes a physical position or preserves an absent name for later native schema evolution.
     * @throws InvalidConstruction When a position is negative or the name is an implicit rowid alias
     */
    public function __construct(public readonly int|Identifier $selector)
    {
        Check::input(
            is_int($selector) ? $selector >= 0 : !in_array($selector->key(), ['rowid', '_rowid_', 'oid'], true),
            'A trigger selector needs a nonnegative column position or a non-rowid absent name.'
        );
    }
}
