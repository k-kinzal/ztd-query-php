<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\Mutation;

use SqlSemantics\Platform\PostgreSql\Ir\ColumnReference;
use SqlSemantics\Platform\PostgreSql\Ir\IntegerLiteral;
use SqlSemantics\Platform\PostgreSql\Ir\NullLiteral;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;

/**
 * A scalar assignment relative to the target row.
 *
 * @visibility public
 * @example Reading the written slot number
 *     $assignment = new \SqlSemantics\Platform\PostgreSql\Ir\Mutation\UpdateAssignment(new \SqlSemantics\Platform\PostgreSql\Ir\ColumnReference(1), new \SqlSemantics\Platform\PostgreSql\Ir\NullLiteral());
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
