<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Ir\Mutation;

use SqlSemantics\Platform\MySql\Ir\ColumnReference;
use SqlSemantics\Platform\MySql\Ir\IntegerLiteral;
use SqlSemantics\Platform\MySql\Ir\NullLiteral;
use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;

/**
 * A scalar assignment relative to the target row.
 *
 * @visibility public
 * @example Reading the written slot number
 *     $assignment = new \SqlSemantics\Platform\MySql\Ir\Mutation\UpdateAssignment(new \SqlSemantics\Platform\MySql\Ir\ColumnReference(1), new \SqlSemantics\Platform\MySql\Ir\NullLiteral());
 *     $assignment->target->slot // => 1
 */
final class UpdateAssignment
{
    use Snapshot;

    /**
     * Closes the write role and any read reference over one input occurrence.
     */
    public function __construct(public readonly ColumnReference $target, public readonly ColumnReference|IntegerLiteral|NullLiteral $value)
    {
        Check::input($target->occurrence === 0, 'An UPDATE target slot must belong to occurrence zero.');
        Check::input(!$value instanceof ColumnReference || $value->occurrence === 0, 'An UPDATE read slot must belong to occurrence zero.');
    }
}
