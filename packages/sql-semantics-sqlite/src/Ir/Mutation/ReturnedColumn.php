<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Ir\Mutation;

use SqlSemantics\Platform\Sqlite\Ir\ColumnReference;
use SqlSemantics\Platform\Sqlite\Ir\Identifier;
use SqlSemantics\Platform\Sqlite\Model\Diagnostic\Check;
use SqlSemantics\Platform\Sqlite\Model\Statement\Snapshot;

/**
 * A named output position reading one field of the affected target row.
 *
 * @visibility public
 * @example Naming the value read from the first target slot
 *     $reference = new \SqlSemantics\Platform\Sqlite\Ir\ColumnReference(0);
 *     $name = new \SqlSemantics\Platform\Sqlite\Ir\Identifier('removed_id');
 *     (new \SqlSemantics\Platform\Sqlite\Ir\Mutation\ReturnedColumn($reference, $name))->name->value // => 'removed_id'
 */
final class ReturnedColumn
{
    use Snapshot;

    /**
     * Checks the statement-local occurrence. The owning mutation checks the slot.
     *
     * @param ColumnReference $reference The target field
     * @param Identifier $name The result label
     */
    public function __construct(public readonly ColumnReference $reference, public readonly Identifier $name)
    {
        Check::input($reference->source === 0, 'A returned column must read the single target occurrence.');
    }
}
