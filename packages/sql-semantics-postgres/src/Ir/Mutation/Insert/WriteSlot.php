<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\Mutation\Insert;

use SqlSemantics\Platform\PostgreSql\Ir\From\ColumnKind;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;

/**
 * One native write position of the single INSERT destination.
 *
 * @visibility public
 * @example Selecting the second text field
 *     $field = new \SqlSemantics\Platform\PostgreSql\Ir\Mutation\Insert\WriteSlot(1, \SqlSemantics\Platform\PostgreSql\Ir\From\ColumnKind::Text);
 *     $field->slot // => 1
 */
final class WriteSlot
{
    use Snapshot;

    /**
     * Checks the coordinate and identifies the native field role.
     */
    public function __construct(public readonly int $slot, public readonly ColumnKind $kind, public readonly int $occurrence = 0)
    {
        Check::input($slot >= 0, 'An INSERT write slot must be nonnegative.');
        Check::input($occurrence === 0, 'An INSERT write slot must belong to the target.');
    }
}
