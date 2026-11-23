<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Ir\Mutation;

use SqlSemantics\Platform\MySql\Ir\From\TextColumnReference;
use SqlSemantics\Platform\MySql\Ir\Literal\TextLiteral;
use SqlSemantics\Platform\MySql\Ir\NullLiteral;
use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;

/**
 * Assigns native text to one VARCHAR slot of the current target row.
 *
 * @visibility public
 * @example Writing NULL to a native text slot
 *     $assignment = new \SqlSemantics\Platform\MySql\Ir\Mutation\TextUpdateAssignment(new \SqlSemantics\Platform\MySql\Ir\From\TextColumnReference(1), new \SqlSemantics\Platform\MySql\Ir\NullLiteral());
 *     $assignment->target->slot // => 1
 */
final class TextUpdateAssignment
{
    use Snapshot;

    /**
     * Restricts both slot roles to the current target occurrence.
     */
    public function __construct(
        public readonly TextColumnReference $target,
        public readonly TextColumnReference|TextLiteral|NullLiteral $value,
    ) {
        Check::input($target->occurrence === 0, 'A VARCHAR UPDATE target slot must belong to occurrence zero.');
        Check::input(!$value instanceof TextColumnReference || $value->occurrence === 0, 'A VARCHAR UPDATE read slot must belong to occurrence zero.');
    }
}
